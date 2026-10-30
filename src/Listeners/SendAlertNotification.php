<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Listeners;

use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Cybernerdie\Glint\Notifications\GlintAlertNotification;

final class SendAlertNotification
{
    public function handle(GlintAlertTriggered $event): void
    {
        $rule = GlintAlertRule::find($event->alertRuleId);

        if ($rule === null) {
            return;
        }

        $rule->notify(new GlintAlertNotification($event));
    }
}
