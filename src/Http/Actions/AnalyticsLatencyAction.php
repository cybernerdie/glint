<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Http\Queries\GenerationPercentilesQuery;
use Cybernerdie\Glint\Http\Queries\TokenThroughputQuery;
use Cybernerdie\Glint\Http\Queries\TracePercentilesQuery;
use Cybernerdie\Glint\Http\Queries\UserLatencyQuery;
use Cybernerdie\Glint\Http\Requests\AnalyticsLatencyRequest;

final class AnalyticsLatencyAction
{
    /** @return array<string, mixed> */
    public function handle(AnalyticsLatencyRequest $request): array
    {
        [$fromDt, $toDt] = $request->dateRange();

        return [
            'tracePercentiles' => rescue(fn () => (new TracePercentilesQuery($fromDt, $toDt))->get(), []),
            'generationPercentiles' => rescue(fn () => (new GenerationPercentilesQuery($fromDt, $toDt))->get(), []),
            'tokenThroughput' => rescue(fn () => (new TokenThroughputQuery($fromDt, $toDt))->get(), collect()),
            'userLatency' => rescue(fn () => (new UserLatencyQuery($fromDt, $toDt))->get(), collect()),
            'period' => $request->period(),
            'fromDate' => $request->fromDate(),
            'toDate' => $request->toDate(),
        ];
    }
}
