<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Concerns\ResolvesDateRange;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

final class CostsController
{
    use ResolvesDateRange;

    /**
     * All figures on this page are derived from glint_generations (the raw
     * source of truth) so totals, breakdowns, and top lists always agree
     * with each other regardless of the selected period.
     */
    public function index(Request $request): ViewContract
    {
        $provider = $request->string('provider')->toString();
        $period = $this->normalizePeriod($request->string('period')->toString());
        $fromDate = $request->string('from')->toString();
        $toDate = $request->string('to')->toString();

        [$fromDt, $toDt] = $this->resolveDateRange($period, $fromDate, $toDate);

        $costByProviderModel = rescue(
            fn () => GlintGeneration::query()
                ->selectRaw('provider, model, SUM(cost_usd) as total_cost, COUNT(*) as total_requests, SUM(total_tokens) as total_tokens')
                ->whereNotNull('cost_usd')
                ->when($provider !== '', fn ($q) => $q->where('provider', $provider))
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->groupBy('provider', 'model')
                ->orderByDesc('total_cost')
                ->get(),
            collect()
        );

        $totalCostRaw = $costByProviderModel->sum('total_cost');
        $totalCost = is_numeric($totalCostRaw) ? (float) $totalCostRaw : 0.0;

        $costTrend = rescue(
            fn () => GlintGeneration::query()
                ->selectRaw('DATE(started_at) as date, SUM(cost_usd) as total')
                ->whereNotNull('cost_usd')
                ->when($provider !== '', fn ($q) => $q->where('provider', $provider))
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->when($fromDt === null && $toDt === null, fn ($q) => $q->where('started_at', '>=', now()->subDays(30)->startOfDay()))
                ->groupByRaw('DATE(started_at)')
                ->orderBy('date')
                ->get(),
            collect()
        );

        $providers = rescue(
            fn () => GlintGeneration::query()->select('provider')->distinct()->orderBy('provider')->pluck('provider'),
            collect()
        );

        $topTraceUseCases = rescue(
            fn () => GlintTrace::query()
                ->select([
                    'glint_traces.name',
                    DB::raw('COUNT(DISTINCT glint_traces.id) as trace_count'),
                    DB::raw('SUM(glint_generations.cost_usd) as total_cost'),
                    DB::raw('SUM(glint_generations.total_tokens) as total_tokens'),
                ])
                ->leftJoin('glint_generations', 'glint_generations.trace_id', '=', 'glint_traces.id')
                ->whereNotNull('glint_traces.name')
                ->when($provider !== '', fn ($q) => $q->where('glint_generations.provider', $provider))
                ->when($fromDt, fn ($q) => $q->where('glint_traces.started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('glint_traces.started_at', '<=', $toDt))
                ->groupBy('glint_traces.name')
                ->orderByDesc('total_cost')
                ->limit(20)
                ->get(),
            collect()
        );

        $topGenerationUseCases = rescue(
            fn () => GlintGeneration::query()
                ->select([
                    'name',
                    DB::raw('COUNT(*) as request_count'),
                    DB::raw('SUM(cost_usd) as total_cost'),
                    DB::raw('SUM(total_tokens) as total_tokens'),
                ])
                ->whereNotNull('name')
                ->when($provider !== '', fn ($q) => $q->where('provider', $provider))
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->groupBy('name')
                ->orderByDesc('total_cost')
                ->limit(20)
                ->get(),
            collect()
        );

        return View::make('glint::costs.index', compact(
            'costByProviderModel', 'totalCost', 'costTrend',
            'topTraceUseCases', 'topGenerationUseCases',
            'provider', 'providers', 'period', 'fromDate', 'toDate'
        ));
    }
}
