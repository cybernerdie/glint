<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Http\Queries\GenerationFiltersQuery;
use Cybernerdie\Glint\Http\Queries\GenerationListQuery;
use Cybernerdie\Glint\Http\Requests\GenerationIndexRequest;

final class GenerationIndexAction
{
    /** @return array<string, mixed> */
    public function handle(GenerationIndexRequest $request): array
    {
        [$fromDt, $toDt] = $request->dateRange();

        $filters = rescue(fn () => (new GenerationFiltersQuery)->get(), ['providers' => collect(), 'models' => collect()]);

        return [
            'generations' => rescue(fn () => (new GenerationListQuery($fromDt, $toDt, $request->provider(), $request->model(), $request->status()))->get(), collect()),
            'providers' => $filters['providers'],
            'models' => $filters['models'],
            'statuses' => RecordStatus::cases(),
            'provider' => $request->provider(),
            'model' => $request->model(),
            'status' => $request->status(),
            'period' => $request->period(),
            'fromDate' => $request->fromDate(),
            'toDate' => $request->toDate(),
        ];
    }
}
