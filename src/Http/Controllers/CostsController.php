<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Actions\CostsIndexAction;
use Cybernerdie\Glint\Http\Requests\CostIndexRequest;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

final class CostsController
{
    public function index(CostIndexRequest $request, CostsIndexAction $action): ViewContract
    {
        return View::make('glint::costs.index', $action->handle($request));
    }
}
