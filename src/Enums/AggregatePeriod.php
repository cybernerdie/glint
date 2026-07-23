<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Enums;

enum AggregatePeriod: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
}
