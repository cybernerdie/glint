<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Alerts\Channels;

use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Support\Facades\Log;

final class LogChannel
{
    public function handle(GlintAlertTriggered $event, GlintAlertRule $rule): void
    {
        Log::warning('[Glint] Alert triggered: '.$rule->name, [
            'rule_id' => $event->alertRuleId,
            'type' => $event->type->value,
            'threshold' => $event->threshold,
            'current_value' => $event->currentValue,
            'period' => $event->period,
        ]);
    }
}
