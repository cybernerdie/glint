<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Enums;

enum AlertChannel: string
{
    case Log = 'log';
    case Mail = 'mail';
    case Webhook = 'webhook';
    case Slack = 'slack';
}
