<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Alerts\Channels;

use Cybernerdie\Glint\Models\GlintAlertRule;
use Cybernerdie\Glint\Notifications\GlintAlertNotification;
use Illuminate\Support\Facades\Log;

final class LogChannel
{
    public function send(GlintAlertRule $rule, GlintAlertNotification $notification): void
    {
        $event = $notification->event;
        Log::warning('[Glint] Alert triggered: '.$rule->name, [
            'rule_id' => $event->alertRuleId,
            'type' => $event->type->value,
            'threshold' => $event->threshold,
            'current_value' => $event->currentValue,
            'period' => $event->period,
        ]);
    }
}
