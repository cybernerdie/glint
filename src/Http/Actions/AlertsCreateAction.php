<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\AlertRuleScope;
use Cybernerdie\Glint\Enums\AlertRuleType;

final class AlertsCreateAction
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return [
            'types' => AlertRuleType::cases(),
            'scopes' => AlertRuleScope::cases(),
            'periods' => AggregatePeriod::cases(),
        ];
    }
}
