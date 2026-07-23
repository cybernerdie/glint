<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Http\Concerns\ResolvesDateRange;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

final class DashboardController
{
    use ResolvesDateRange;

    /**
     * All figures on this page are derived from the raw glint_traces and
     * glint_generations tables (single source of truth) so every panel
     * agrees with every other panel for any selected period.
     */
    public function index(Request $request): ViewContract
    {
        $period = $this->normalizePeriod($request->string('period')->toString());
        $fromDate = $request->string('from')->toString();
        $toDate = $request->string('to')->toString();

        [$fromDt, $toDt] = $this->resolveDateRange($period, $fromDate, $toDate);

        $cacheKey = '__glint__:dashboard.stats.'.$period.'.'.$fromDate.'.'.$toDate;

        $emptyStats = [
            'total_traces' => 0,
            'total_generations' => 0,
            'total_cost_usd' => 0.0,
            'avg_duration_ms' => 0,
            'error_rate' => 0.0,
        ];

        $stats = rescue(fn () => Cache::remember($cacheKey, 60, function () use ($fromDt, $toDt, $emptyStats) {
            return rescue(function () use ($fromDt, $toDt) {
                $genQuery = GlintGeneration::query()
                    ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                    ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt));

                $row = (clone $genQuery)
                    ->selectRaw(
                        'COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as errors, SUM(cost_usd) as total_cost, AVG(duration_ms) as avg_duration',
                        [RecordStatus::Error->value]
                    )
                    ->first();

                $attrs = $row ? $row->getAttributes() : [];
                $total = isset($attrs['total']) && is_numeric($attrs['total']) ? (int) $attrs['total'] : 0;
                $errors = isset($attrs['errors']) && is_numeric($attrs['errors']) ? (int) $attrs['errors'] : 0;
                $totalCost = isset($attrs['total_cost']) && is_numeric($attrs['total_cost']) ? (float) $attrs['total_cost'] : 0.0;
                $avgDuration = isset($attrs['avg_duration']) && is_numeric($attrs['avg_duration']) ? (int) $attrs['avg_duration'] : 0;
                $errorRate = $total > 0 ? round(($errors / $total) * 100, 1) : 0.0;

                $traceQuery = GlintTrace::query()
                    ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                    ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt));

                return [
                    'total_traces' => $traceQuery->count(),
                    'total_generations' => $total,
                    'total_cost_usd' => $totalCost,
                    'avg_duration_ms' => $avgDuration,
                    'error_rate' => $errorRate,
                ];
            }, $emptyStats);
        }), $emptyStats);

        $key = '__glint__:dashboard.panels.'.$period.'.'.$fromDate.'.'.$toDate;

        $volumeBuckets = rescue(fn () => Cache::remember($key.'.volume', 60, fn () => $this->volumeBuckets($period, $fromDt, $toDt)), collect());

        $recentTraces = rescue(
            fn () => Cache::remember($key.'.recent', 60, fn () => GlintTrace::query()->latest('started_at')->limit(10)->get()),
            collect()
        );

        $topTraceNames = rescue(
            fn () => Cache::remember($key.'.traceNames', 60, fn () => GlintTrace::query()
                ->select(['name', DB::raw('COUNT(*) as trace_count')])
                ->whereNotNull('name')
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->groupBy('name')
                ->orderByDesc('trace_count')
                ->limit(8)
                ->get()),
            collect()
        );

        $topModelCosts = rescue(
            fn () => Cache::remember($key.'.modelCosts', 60, fn () => GlintGeneration::query()
                ->select([
                    'model',
                    'provider',
                    DB::raw('SUM(cost_usd) as total_cost'),
                    DB::raw('SUM(total_tokens) as total_tokens'),
                    DB::raw('COUNT(*) as total_requests'),
                ])
                ->whereNotNull('cost_usd')
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->groupBy('model', 'provider')
                ->orderByDesc('total_cost')
                ->limit(6)
                ->get()),
            collect()
        );

        $topUserCosts = rescue(
            fn () => Cache::remember($key.'.userCosts', 60, fn () => GlintTrace::query()
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
                ->get()),
            collect()
        );

        return View::make('glint::dashboard', compact(
            'stats', 'recentTraces', 'volumeBuckets',
            'topTraceNames', 'topModelCosts', 'topUserCosts',
            'period', 'fromDate', 'toDate'
        ));
    }

    /**
     * Build chart buckets for the request-volume chart from raw generations.
     *
     * Last 24 hours => hourly buckets (grouped in PHP for DB portability),
     * otherwise => one bucket per day across the selected range.
     *
     * @return Collection<int, array{label: string, total: int}>
     */
    private function volumeBuckets(string $period, ?Carbon $fromDt, ?Carbon $toDt): Collection
    {
        if ($period === '24h') {
            $rows = GlintGeneration::query()
                ->selectRaw($this->hourBucketExpression().' as hour_key, COUNT(*) as total')
                ->where('started_at', '>=', now()->subHours(24))
                ->groupByRaw($this->hourBucketExpression())
                ->get();

            /** @var array<string, int> $hourlyTotals */
            $hourlyTotals = [];
            foreach ($rows as $row) {
                $key = $row->getAttribute('hour_key');
                $total = $row->getAttribute('total');
                if (is_string($key) && $key !== '') {
                    $hourlyTotals[$key] = is_numeric($total) ? (int) $total : 0;
                }
            }

            $buckets = [];
            for ($h = 23; $h >= 0; $h--) {
                $hour = now()->subHours($h);
                $buckets[] = [
                    'label' => $h % 4 === 0 ? $hour->format('ga') : '',
                    'total' => $hourlyTotals[$hour->format('Y-m-d H')] ?? 0,
                ];
            }

            return collect($buckets);
        }

        $rows = GlintGeneration::query()
            ->selectRaw('DATE(started_at) as date, COUNT(*) as total')
            ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
            ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
            ->when($fromDt === null && $toDt === null, fn ($q) => $q->where('started_at', '>=', now()->subDays(13)->startOfDay()))
            ->groupByRaw('DATE(started_at)')
            ->orderBy('date')
            ->get();

        /** @var array<string, int> $dailyTotals */
        $dailyTotals = [];
        foreach ($rows as $row) {
            $date = $row->getAttribute('date');
            $total = $row->getAttribute('total');

            if (is_string($date) && $date !== '') {
                $dailyTotals[$date] = is_numeric($total) ? (int) $total : 0;
            }
        }

        // Anchor the window on the end of the selected range so custom
        // ranges chart their own days instead of a window ending today.
        $rangeEnd = ($toDt ?? now())->copy()->startOfDay();

        $dayCount = match ($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            // Custom/unknown: span the selected range (capped), else two weeks.
            default => $fromDt !== null
                ? min(120, (int) $fromDt->copy()->startOfDay()->diffInDays($rangeEnd) + 1)
                : 14,
        };

        $buckets = [];
        for ($i = $dayCount - 1; $i >= 0; $i--) {
            $day = $rangeEnd->copy()->subDays($i);
            $buckets[] = [
                'label' => $dayCount <= 14
                    ? $day->format('M j')
                    : ($i % 7 === 0 ? $day->format('M j') : ''),
                'total' => $dailyTotals[$day->format('Y-m-d')] ?? 0,
            ];
        }

        return collect($buckets);
    }

    /**
     * A driver-portable SQL expression producing an "Y-m-d H" hour key that
     * matches the PHP-side bucket keys.
     */
    /** @return literal-string */
    private function hourBucketExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT(started_at, '%Y-%m-%d %H')",
            'pgsql' => "to_char(started_at, 'YYYY-MM-DD HH24')",
            default => "strftime('%Y-%m-%d %H', started_at)",
        };
    }
}
