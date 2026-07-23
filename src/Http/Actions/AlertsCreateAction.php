<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Illuminate\Support\Facades\Config;

final class AlertsCreateAction
{
    /** @return array<string, mixed> */
    public function handle(): array
    {
        return [
            'types' => AlertRuleType::cases(),
            'periods' => AggregatePeriod::cases(),
            'providers' => array_values(array_unique(array_filter(array_map(fn (mixed $v): string => is_string($v) ? $v : '', Config::array('glint.llm_hosts'))))),
        ];
    }
}
