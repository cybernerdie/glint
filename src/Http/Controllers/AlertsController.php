<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Actions\AlertsCreateAction;
use Cybernerdie\Glint\Http\Actions\AlertsIndexAction;
use Cybernerdie\Glint\Http\Actions\DestroyAlertRuleAction;
use Cybernerdie\Glint\Http\Actions\StoreAlertRuleAction;
use Cybernerdie\Glint\Http\Actions\ToggleAlertRuleAction;
use Cybernerdie\Glint\Http\Requests\StoreAlertRuleRequest;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\View;

final class AlertsController
{
    public function index(AlertsIndexAction $action): ViewContract
    {
        return View::make('glint::alerts.index', $action->handle());
    }

    public function create(AlertsCreateAction $action): ViewContract
    {
        return View::make('glint::alerts.create', $action->handle());
    }

    public function store(StoreAlertRuleRequest $request, StoreAlertRuleAction $action): RedirectResponse
    {
        $action->handle($request);

        return redirect()->route('glint.alerts.index');
    }

    public function toggle(string $alertRuleId, ToggleAlertRuleAction $action): RedirectResponse
    {
        $action->handle($alertRuleId);

        return redirect()->route('glint.alerts.index');
    }

    public function destroy(string $alertRuleId, DestroyAlertRuleAction $action): RedirectResponse
    {
        $action->handle($alertRuleId);

        return redirect()->route('glint.alerts.index');
    }
}
