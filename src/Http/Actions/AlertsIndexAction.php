<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Models\GlintAlertEvent;
use Cybernerdie\Glint\Models\GlintAlertRule;

final class AlertsIndexAction
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return [
            'rules'        => rescue(fn () => GlintAlertRule::query()->latest()->paginate(25), collect()),
            'recentEvents' => rescue(fn () => GlintAlertEvent::query()->with('rule')->latest('triggered_at')->limit(50)->get(), collect()),
        ];
    }
}
