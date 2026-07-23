<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Http\Queries\CostBreakdownQuery;
use Cybernerdie\Glint\Http\Queries\CostProvidersQuery;
use Cybernerdie\Glint\Http\Queries\CostTrendQuery;
use Cybernerdie\Glint\Http\Queries\TopGenerationUseCasesQuery;
use Cybernerdie\Glint\Http\Queries\TopTraceUseCasesQuery;
use Cybernerdie\Glint\Http\Requests\CostIndexRequest;

final class CostsIndexAction
{
    /** @return array<string, mixed> */
    public function handle(CostIndexRequest $request): array
    {
        [$fromDt, $toDt] = $request->dateRange();
        $provider = $request->provider();

        $costByProviderModel = rescue(fn () => (new CostBreakdownQuery($fromDt, $toDt, $provider))->get(), collect());
        $totalCostRaw = $costByProviderModel->sum('total_cost');

        return [
            'costByProviderModel' => $costByProviderModel,
            'totalCost' => is_numeric($totalCostRaw) ? (float) $totalCostRaw : 0.0,
            'costTrend' => rescue(fn () => (new CostTrendQuery($fromDt, $toDt, $provider))->get(), collect()),
            'providers' => rescue(fn () => (new CostProvidersQuery)->get(), collect()),
            'topTraceUseCases' => rescue(fn () => (new TopTraceUseCasesQuery($fromDt, $toDt, $provider))->get(), collect()),
            'topGenerationUseCases' => rescue(fn () => (new TopGenerationUseCasesQuery($fromDt, $toDt, $provider))->get(), collect()),
            'provider' => $provider,
            'period' => $request->period(),
            'fromDate' => $request->fromDate(),
            'toDate' => $request->toDate(),
        ];
    }
}
