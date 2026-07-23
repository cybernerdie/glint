<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Actions\DashboardIndexAction;
use Cybernerdie\Glint\Http\Requests\DashboardRequest;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

final class DashboardController
{
    public function index(DashboardRequest $request, DashboardIndexAction $action): ViewContract
    {
        return View::make('glint::dashboard', $action->handle($request));
    }
}
