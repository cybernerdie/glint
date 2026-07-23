<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Actions\GenerationIndexAction;
use Cybernerdie\Glint\Http\Actions\GenerationShowAction;
use Cybernerdie\Glint\Http\Requests\GenerationIndexRequest;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

final class GenerationsController
{
    public function index(GenerationIndexRequest $request, GenerationIndexAction $action): ViewContract
    {
        return View::make('glint::generations.index', $action->handle($request));
    }

    public function show(string $generationId, GenerationShowAction $action): ViewContract
    {
        return View::make('glint::generations.show', $action->handle($generationId));
    }
}
