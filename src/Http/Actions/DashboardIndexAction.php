<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Http\Queries\DashboardStatsQuery;
use Cybernerdie\Glint\Http\Queries\RecentTracesQuery;
use Cybernerdie\Glint\Http\Queries\TopModelCostsQuery;
use Cybernerdie\Glint\Http\Queries\TopTraceNamesQuery;
use Cybernerdie\Glint\Http\Queries\TopUserCostsQuery;
use Cybernerdie\Glint\Http\Queries\VolumeBucketsQuery;
use Cybernerdie\Glint\Http\Requests\DashboardRequest;

final class DashboardIndexAction
{
    /** @return array<string, mixed> */
    public function handle(DashboardRequest $request): array
    {
        [$fromDt, $toDt] = $request->dateRange();
        $period = $request->period();

        return [
            'stats'         => rescue(fn () => (new DashboardStatsQuery($fromDt, $toDt))->get(), DashboardStatsQuery::empty()),
            'volumeBuckets' => rescue(fn () => (new VolumeBucketsQuery($period, $fromDt, $toDt))->get(), collect()),
            'recentTraces'  => rescue(fn () => (new RecentTracesQuery($fromDt, $toDt))->get(), collect()),
            'topTraceNames' => rescue(fn () => (new TopTraceNamesQuery($fromDt, $toDt))->get(), collect()),
            'topModelCosts' => rescue(fn () => (new TopModelCostsQuery($fromDt, $toDt))->get(), collect()),
            'topUserCosts'  => rescue(fn () => (new TopUserCostsQuery($fromDt, $toDt))->get(), collect()),
            'period'        => $period,
            'fromDate'      => $request->fromDate(),
            'toDate'        => $request->toDate(),
        ];
    }
}
