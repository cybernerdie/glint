<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Support\Facades\Config;

final class AlertsEditAction
{
    /** @return array<string, mixed> */
    public function handle(GlintAlertRule $rule): array
    {
        $config = $rule->threshold_config ?? [];

        return [
            'rule' => $rule,
            'types' => AlertRuleType::cases(),
            'periods' => AggregatePeriod::cases(),
            'providers' => array_values(array_unique(array_filter(array_map(fn (mixed $v): string => is_string($v) ? $v : '', Config::array('glint.llm_hosts'))))),
            'oldThreshold' => is_numeric($config['threshold'] ?? null) ? (string) $config['threshold'] : '',
            'oldPeriod' => is_string($config['period'] ?? null) ? $config['period'] : 'day',
            'oldProvider' => is_string($config['provider'] ?? null) ? $config['provider'] : '',
        ];
    }
}
