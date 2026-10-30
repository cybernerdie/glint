<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Alerts\Channels;

use Cybernerdie\Glint\Enums\AlertChannel;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Cybernerdie\Glint\Notifications\GlintAlertNotification;
use Illuminate\Support\Facades\Http;

final class SlackChannel
{
    public function send(GlintAlertRule $rule, GlintAlertNotification $notification): void
    {
        $destination = $rule->notificationDestination(AlertChannel::Slack);

        if ($destination === null) {
            return;
        }

        $event = $notification->event;
        $value = $this->formatValue($event);

        Http::timeout(10)->post($destination, [
            'text' => "🚨 *Glint Alert: {$rule->name}*",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*🚨 Glint Alert: {$rule->name}*\n{$value} exceeded threshold of {$this->formatThreshold($event)} over the last {$event->period}.",
                    ],
                ],
            ],
        ])->throw();
    }

    private function formatValue(GlintAlertTriggered $event): string
    {
        return match ($event->type) {
            AlertRuleType::CostThreshold => '$'.number_format($event->currentValue, 4),
            AlertRuleType::ErrorRate => number_format($event->currentValue, 1).'%',
            AlertRuleType::LatencySpike => number_format($event->currentValue).'ms',
            AlertRuleType::TokenSpike => number_format($event->currentValue).' tokens',
        };
    }

    private function formatThreshold(GlintAlertTriggered $event): string
    {
        return match ($event->type) {
            AlertRuleType::CostThreshold => '$'.number_format($event->threshold, 4),
            AlertRuleType::ErrorRate => number_format($event->threshold, 1).'%',
            AlertRuleType::LatencySpike => number_format($event->threshold).'ms',
            AlertRuleType::TokenSpike => number_format($event->threshold).' tokens',
        };
    }
}
