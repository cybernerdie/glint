<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Http\Concerns\ResolvesDateRange;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

final class DashboardController
{
    use ResolvesDateRange;

    public function index(Request $request): ViewContract
    {
        $period = $request->string('period')->toString() ?: 'today';
        $fromDate = $request->string('from')->toString();
        $toDate = $request->string('to')->toString();

        [$fromDt, $toDt] = $this->resolveDateRange($period, $fromDate, $toDate);

        $cacheKey = '__glint__:dashboard.stats.'.$period.'.'.$fromDate.'.'.$toDate;

        $stats = rescue(fn () => Cache::remember($cacheKey, 60, function () use ($fromDt, $toDt) {
            return rescue(function () use ($fromDt, $toDt) {
                $aggQuery = GlintAggregate::query();
                if ($fromDt !== null) {
                    $aggQuery->where('period_at', '>=', $fromDt);
                }
                if ($toDt !== null) {
                    $aggQuery->where('period_at', '<=', $toDt);
                }

                $row = (clone $aggQuery)
                    ->selectRaw('SUM(total_requests) as total, SUM(failed_requests) as errors, SUM(total_cost_usd) as total_cost, SUM(avg_duration_ms * total_requests) / NULLIF(SUM(total_requests), 0) as avg_duration')
                    ->first();

                $attrs = $row ? $row->getAttributes() : [];
                $total = isset($attrs['total']) && is_numeric($attrs['total']) ? (int) $attrs['total'] : 0;
                $errors = isset($attrs['errors']) && is_numeric($attrs['errors']) ? (int) $attrs['errors'] : 0;
                $totalCost = isset($attrs['total_cost']) && is_numeric($attrs['total_cost']) ? (float) $attrs['total_cost'] : 0.0;
                $avgDuration = isset($attrs['avg_duration']) && is_numeric($attrs['avg_duration']) ? (int) $attrs['avg_duration'] : 0;
                $errorRate = $total > 0 ? round(($errors / $total) * 100, 1) : 0.0;

                $traceQuery = GlintTrace::query();
                if ($fromDt !== null) {
                    $traceQuery->where('started_at', '>=', $fromDt);
                }
                if ($toDt !== null) {
                    $traceQuery->where('started_at', '<=', $toDt);
                }

                return [
                    'total_traces' => $traceQuery->count(),
                    'total_generations' => $total,
                    'total_cost_usd' => $totalCost,
                    'avg_duration_ms' => $avgDuration,
                    'error_rate' => $errorRate,
                ];
            }, [
                'total_traces' => 0,
                'total_generations' => 0,
                'total_cost_usd' => 0.0,
                'avg_duration_ms' => 0,
                'error_rate' => 0.0,
            ]);
        }), [
            'total_traces' => 0,
            'total_generations' => 0,
            'total_cost_usd' => 0.0,
            'avg_duration_ms' => 0,
            'error_rate' => 0.0,
        ]);

        $recentTraces = rescue(
            fn () => GlintTrace::query()->latest('started_at')->limit(10)->get(),
            collect()
        );

        $dailyVolume = rescue(
            fn () => GlintAggregate::query()
                ->selectRaw('DATE(period_at) as date, SUM(total_requests) as total, SUM(total_cost_usd) as cost')
                ->where('period', AggregatePeriod::Day->value)
                ->when($fromDt, fn ($q) => $q->where('period_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('period_at', '<=', $toDt))
                ->when($fromDt === null && $toDt === null, fn ($q) => $q->where('period_at', '>=', now()->subDays(13)->startOfDay()))
                ->groupByRaw('DATE(period_at)')
                ->orderBy('date')
                ->get(),
            collect()
        );

        $topTraceNames = rescue(
            fn () => GlintTrace::query()
                ->select(['name', DB::raw('COUNT(*) as trace_count')])
                ->whereNotNull('name')
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->groupBy('name')
                ->orderByDesc('trace_count')
                ->limit(8)
                ->get(),
            collect()
        );

        $topModelCosts = rescue(
            fn () => GlintAggregate::query()
                ->select([
                    'model',
                    'provider',
                    DB::raw('SUM(total_cost_usd) as total_cost'),
                    DB::raw('SUM(total_tokens) as total_tokens'),
                    DB::raw('SUM(total_requests) as total_requests'),
                ])
                ->when($fromDt, fn ($q) => $q->where('period_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('period_at', '<=', $toDt))
                ->groupBy('model', 'provider')
                ->orderByDesc('total_cost')
                ->limit(6)
                ->get(),
            collect()
        );

        $topUserCosts = rescue(
            fn () => GlintTrace::query()
                ->select([
                    'glint_traces.user_id',
                    DB::raw('SUM(glint_generations.cost_usd) as total_cost'),
                    DB::raw('COUNT(DISTINCT glint_traces.id) as trace_count'),
                ])
                ->leftJoin('glint_generations', 'glint_generations.trace_id', '=', 'glint_traces.id')
                ->whereNotNull('glint_traces.user_id')
                ->when($fromDt, fn ($q) => $q->where('glint_traces.started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('glint_traces.started_at', '<=', $toDt))
                ->groupBy('glint_traces.user_id')
                ->orderByDesc('total_cost')
                ->limit(8)
                ->get(),
            collect()
        );

        return View::make('glint::dashboard', compact(
            'stats', 'recentTraces', 'dailyVolume',
            'topTraceNames', 'topModelCosts', 'topUserCosts',
            'period', 'fromDate', 'toDate'
        ));
    }
}
