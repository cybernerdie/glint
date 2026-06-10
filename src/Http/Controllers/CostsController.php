<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Models\GlintAggregate;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

final class CostsController
{
    public function index(): ViewContract
    {
        // Use the pre-aggregated glint_aggregates table for the per-provider/model breakdown
        // to avoid an expensive full-table GROUP BY scan on glint_generations.
        $costByProviderModel = rescue(
            fn () => GlintAggregate::query()
                ->selectRaw('provider, model, SUM(total_cost_usd) as total_cost, SUM(total_requests) as total_requests, SUM(total_tokens) as total_tokens')
                ->groupBy('provider', 'model')
                ->orderByDesc('total_cost')
                ->get(),
            collect()
        );

        // Derive total cost from aggregates as well — avoids a second raw scan.
        $totalCost = rescue(
            fn () => (float) GlintAggregate::query()->sum('total_cost_usd'),
            0.0
        );

        $dailyAggregates = rescue(
            fn () => GlintAggregate::query()
                ->where('period', AggregatePeriod::Day)
                ->where('period_at', '>=', now()->subDays(30))
                ->orderBy('period_at')
                ->get(),
            collect()
        );

        return View::make('glint::costs.index', compact('costByProviderModel', 'totalCost', 'dailyAggregates'));
    }
}
