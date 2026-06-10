<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Enums;

enum AlertRuleType: string
{
    case CostThreshold = 'cost_threshold';
    case ErrorRate = 'error_rate';
    case LatencySpike = 'latency_spike';
    case TokenSpike = 'token_spike';
}
