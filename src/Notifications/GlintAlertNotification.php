<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Notifications;

use Cybernerdie\Glint\Alerts\Channels\LogChannel;
use Cybernerdie\Glint\Alerts\Channels\SlackChannel;
use Cybernerdie\Glint\Alerts\Channels\WebhookChannel;
use Cybernerdie\Glint\Enums\AlertChannel;
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Mail\GlintAlertMail;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Notifications\Notification;

final class GlintAlertNotification extends Notification
{
    public function __construct(public readonly GlintAlertTriggered $event) {}

    /** @return array<int, string> */
    public function via(GlintAlertRule $rule): array
    {
        return match ($this->event->channel) {
            AlertChannel::Log => [LogChannel::class],
            AlertChannel::Mail => $rule->hasNotificationDestination(AlertChannel::Mail) ? [AlertChannel::Mail->value] : [],
            AlertChannel::Webhook => $rule->hasNotificationDestination(AlertChannel::Webhook) ? [WebhookChannel::class] : [],
            AlertChannel::Slack => $rule->hasNotificationDestination(AlertChannel::Slack) ? [SlackChannel::class] : [],
        };
    }

    public function toMail(GlintAlertRule $rule): GlintAlertMail
    {
        $destination = $rule->notificationDestination(AlertChannel::Mail);

        if ($destination === null) {
            throw new \LogicException('A mail destination is required to send a Glint alert.');
        }

        return (new GlintAlertMail($this->event, $rule))->to($destination);
    }
}
