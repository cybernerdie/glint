<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Http\Concerns\ResolvesDateRange;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

final class CostsController
{
    use ResolvesDateRange;

    public function index(Request $request): ViewContract
    {
        $provider = $request->string('provider')->toString();
        $period   = $request->string('period')->toString() ?: 'today';
        $fromDate = $request->string('from')->toString();
        $toDate   = $request->string('to')->toString();

        [$fromDt, $toDt] = $this->resolveDateRange($period, $fromDate, $toDate);

        // Use the pre-aggregated glint_aggregates table for the per-provider/model breakdown
        // to avoid an expensive full-table GROUP BY scan on glint_generations.
        $costByProviderModel = rescue(
            fn () => GlintAggregate::query()
                ->selectRaw('provider, model, SUM(total_cost_usd) as total_cost, SUM(total_requests) as total_requests, SUM(total_tokens) as total_tokens')
                ->when($provider !== '', fn ($q) => $q->where('provider', $provider))
                ->when($fromDt, fn ($q) => $q->where('period_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('period_at', '<=', $toDt))
                ->groupBy('provider', 'model')
                ->orderByDesc('total_cost')
                ->get(),
            collect()
        );

        // Derive total cost from same filtered set.
        $totalCost = rescue(
            fn () => GlintAggregate::query()
                ->when($provider !== '', fn ($q) => $q->where('provider', $provider))
                ->when($fromDt, fn ($q) => $q->where('period_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('period_at', '<=', $toDt))
                ->sum('total_cost_usd'),
            0.0
        );

        $dailyAggregates = rescue(
            fn () => GlintAggregate::query()
                ->where('period', AggregatePeriod::Day)
                ->when($provider !== '', fn ($q) => $q->where('provider', $provider))
                ->when($fromDt, fn ($q) => $q->where('period_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('period_at', '<=', $toDt))
                ->when($fromDt === null && $toDt === null, fn ($q) => $q->where('period_at', '>=', now()->subDays(30)))
                ->orderBy('period_at')
                ->get(),
            collect()
        );

        $providers = rescue(
            fn () => GlintAggregate::query()->select('provider')->distinct()->orderBy('provider')->pluck('provider'),
            collect()
        );

        // Top trace use cases by cost (join traces → generations)
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

        // Top generation names by cost
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
            'costByProviderModel', 'totalCost', 'dailyAggregates',
            'topTraceUseCases', 'topGenerationUseCases',
            'provider', 'providers', 'period', 'fromDate', 'toDate'
        ));
    }
}
