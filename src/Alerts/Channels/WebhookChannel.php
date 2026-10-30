<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Alerts\Channels;

use Cybernerdie\Glint\Enums\AlertChannel;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Cybernerdie\Glint\Notifications\GlintAlertNotification;
use Illuminate\Support\Facades\Http;

final class WebhookChannel
{
    public function send(GlintAlertRule $rule, GlintAlertNotification $notification): void
    {
        $destination = $rule->notificationDestination(AlertChannel::Webhook);

        if ($destination === null) {
            return;
        }

        $event = $notification->event;

        Http::timeout(10)->post($destination, [
            'alert_event_id' => $event->alertEventId,
            'alert_rule_id' => $event->alertRuleId,
            'rule_name' => $rule->name,
            'type' => $event->type->value,
            'threshold' => $event->threshold,
            'current_value' => $event->currentValue,
            'period' => $event->period,
            'triggered_at' => now()->toIso8601String(),
        ])->throw();
    }
}
