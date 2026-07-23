<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Listeners;

use Cybernerdie\Glint\Alerts\Channels\LogChannel;
use Cybernerdie\Glint\Alerts\Channels\MailChannel;
use Cybernerdie\Glint\Alerts\Channels\SlackChannel;
use Cybernerdie\Glint\Alerts\Channels\WebhookChannel;
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Models\GlintAlertRule;

final class SendAlertNotification
{
    public function handle(GlintAlertTriggered $event): void
    {
        $rule = GlintAlertRule::find($event->alertRuleId);

        if ($rule === null) {
            return;
        }

        match ($event->channel) {
            'log' => app(LogChannel::class)->handle($event, $rule),
            'mail' => app(MailChannel::class)->handle($event, $rule),
            'webhook' => app(WebhookChannel::class)->handle($event, $rule),
            'slack' => app(SlackChannel::class)->handle($event, $rule),
            default => null,
        };
    }
}
