<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Events;

use Cybernerdie\Glint\Enums\AlertRuleType;

final readonly class GlintAlertTriggered
{
    public function __construct(
        public int $alertRuleId,
        public AlertRuleType $type,
        public float $threshold,
        public float $currentValue,
        public string $period,
        public string $channel,
        public int $alertEventId,
    ) {}
}
