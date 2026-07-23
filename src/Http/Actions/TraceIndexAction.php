<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Http\Queries\TraceListQuery;
use Cybernerdie\Glint\Http\Requests\TraceIndexRequest;

final class TraceIndexAction
{
    /** @return array<string, mixed> */
    public function handle(TraceIndexRequest $request): array
    {
        [$fromDt, $toDt] = $request->dateRange();

        return [
            'traces' => rescue(fn () => (new TraceListQuery($fromDt, $toDt, $request->search(), $request->status(), $request->userId()))->get(), collect()),
            'statuses' => RecordStatus::cases(),
            'search' => $request->search(),
            'status' => $request->status(),
            'userId' => $request->userId(),
            'period' => $request->period(),
            'fromDate' => $request->fromDate(),
            'toDate' => $request->toDate(),
        ];
    }
}
