<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Enums;

enum AlertRuleScope: string
{
    case Global = 'global';
    case User = 'user';
    case Team = 'team';
    case Provider = 'provider';
    case Model = 'model';
}
