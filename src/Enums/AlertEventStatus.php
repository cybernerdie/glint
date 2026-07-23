<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Enums;

enum AlertEventStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
