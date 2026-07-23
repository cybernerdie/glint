<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Alerts\Channels;

use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Support\Facades\Http;

final class WebhookChannel
{
    public function handle(GlintAlertTriggered $event, GlintAlertRule $rule): void
    {
        if (! is_string($rule->webhook_url) || $rule->webhook_url === '') {
            return;
        }

        Http::timeout(10)->post($rule->webhook_url, [
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
