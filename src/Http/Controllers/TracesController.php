<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Actions\TraceIndexAction;
use Cybernerdie\Glint\Http\Actions\TraceShowAction;
use Cybernerdie\Glint\Http\Requests\TraceIndexRequest;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

final class TracesController
{
    public function index(TraceIndexRequest $request, TraceIndexAction $action): ViewContract
    {
        return View::make('glint::traces.index', $action->handle($request));
    }

    public function show(string $traceId, TraceShowAction $action): ViewContract
    {
        return View::make('glint::traces.show', $action->handle($traceId));
    }
}
