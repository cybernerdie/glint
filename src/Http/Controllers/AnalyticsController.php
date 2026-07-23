<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Actions\AnalyticsLatencyAction;
use Cybernerdie\Glint\Http\Requests\BaseFilterRequest;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

final class AnalyticsController
{
    public function __invoke(BaseFilterRequest $request, AnalyticsLatencyAction $action): ViewContract
    {
        return View::make('glint::analytics.latency', $action->handle($request));
    }
}
