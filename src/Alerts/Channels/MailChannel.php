<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Alerts\Channels;

use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Mail\GlintAlertMail;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Support\Facades\Mail;

final class MailChannel
{
    public function handle(GlintAlertTriggered $event, GlintAlertRule $rule): void
    {
        if (! is_string($rule->mail_to) || $rule->mail_to === '') {
            return;
        }

        Mail::to($rule->mail_to)->send(new GlintAlertMail($event, $rule));
    }
}
