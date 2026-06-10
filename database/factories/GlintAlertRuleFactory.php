<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Database\Factories;

use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlintAlertRule>
 */
final class GlintAlertRuleFactory extends Factory
{
    protected $model = GlintAlertRule::class;

    public function definition(): array
    {
        return [
            'name' => 'Test Alert Rule',
            'type' => AlertRuleType::CostThreshold,
            'threshold_config' => ['threshold' => 10.0, 'period' => 'day'],
            'channels' => ['log'],
            'enabled' => true,
            'cooldown_minutes' => 60,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['enabled' => false]);
    }

    public function withinCooldown(): static
    {
        return $this->state(['last_triggered_at' => now()->subMinutes(10)]);
    }

    public function cooldownElapsed(): static
    {
        return $this->state(['last_triggered_at' => now()->subMinutes(120)]);
    }
}
