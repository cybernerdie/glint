<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\AlertRuleScope;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Models\GlintAlertEvent;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;

final class AlertsController
{
    public function index(): ViewContract
    {
        $rules = rescue(
            fn () => GlintAlertRule::query()->latest()->paginate(25),
            collect()
        );

        $recentEvents = rescue(
            fn () => GlintAlertEvent::query()
                ->with('rule')
                ->latest('triggered_at')
                ->limit(50)
                ->get(),
            collect()
        );

        return View::make('glint::alerts.index', compact('rules', 'recentEvents'));
    }

    public function create(): ViewContract
    {
        $types = AlertRuleType::cases();
        $scopes = AlertRuleScope::cases();
        $periods = AggregatePeriod::cases();

        return View::make('glint::alerts.create', compact('types', 'scopes', 'periods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_column(AlertRuleType::cases(), 'value'))],
            'threshold' => ['required', 'numeric', 'min:0'],
            'period' => ['required', Rule::in(array_column(AggregatePeriod::cases(), 'value'))],
            'scope' => ['required', Rule::in(array_column(AlertRuleScope::cases(), 'value'))],
            'scope_id' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(['mail', 'webhook', 'slack', 'log'])],
            'mail_to' => [
                Rule::requiredIf(fn () => in_array('mail', (array) $request->input('channels', []), true)),
                'nullable', 'email', 'max:255',
            ],
            'webhook_url' => [
                Rule::requiredIf(fn () => in_array('webhook', (array) $request->input('channels', []), true)),
                'nullable', 'url', 'max:500',
            ],
            'cooldown_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'enabled' => ['boolean'],
        ]);

        $name = $request->string('name')->toString();
        $type = $request->string('type')->toString();
        $scope = $request->string('scope')->toString();
        $scopeId = $request->string('scope_id')->toString() ?: null;
        $provider = $request->string('provider')->toString() ?: null;
        $thresholdRaw = $request->input('threshold', 0);
        $threshold = is_numeric($thresholdRaw) ? (float) $thresholdRaw : 0.0;
        $period = $request->string('period')->toString();
        $rawChannels = $request->input('channels', []);
        $channels = array_values(array_map('strval', array_filter(is_array($rawChannels) ? $rawChannels : [], 'is_scalar')));
        $mailTo = $request->string('mail_to')->toString() ?: null;
        $webhookUrl = $request->string('webhook_url')->toString() ?: null;
        $cooldown = $request->integer('cooldown_minutes');
        $enabled = (bool) $request->input('enabled', true);

        rescue(function () use (
            $name, $type, $scope, $scopeId, $provider,
            $threshold, $period, $channels,
            $mailTo, $webhookUrl, $cooldown, $enabled
        ): void {
            GlintAlertRule::create([
                'name' => $name,
                'type' => $type,
                'scope' => $scope,
                'scope_id' => $scopeId,
                'threshold_config' => [
                    'threshold' => $threshold,
                    'period' => $period,
                    'provider' => $provider,
                ],
                'channels' => $channels,
                'mail_to' => in_array('mail', $channels, true) ? $mailTo : null,
                'webhook_url' => in_array('webhook', $channels, true) ? $webhookUrl : null,
                'cooldown_minutes' => $cooldown,
                'enabled' => $enabled,
            ]);
        });

        return redirect()->route('glint.alerts.index');
    }

    public function toggle(string $alertRuleId): RedirectResponse
    {
        $rule = rescue(fn () => GlintAlertRule::query()->find($alertRuleId), null);

        if ($rule === null) {
            abort(404);
        }

        rescue(fn () => $rule->update(['enabled' => ! $rule->enabled]));

        return redirect()->route('glint.alerts.index');
    }

    public function destroy(string $alertRuleId): RedirectResponse
    {
        $rule = rescue(fn () => GlintAlertRule::query()->find($alertRuleId), null);

        rescue(fn () => $rule?->delete());

        return redirect()->route('glint.alerts.index');
    }
}
