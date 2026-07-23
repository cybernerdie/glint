<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Database\Factories;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlintTrace>
 */
final class GlintTraceFactory extends Factory
{
    protected $model = GlintTrace::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'status' => RecordStatus::Success,
            'started_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => RecordStatus::Pending]);
    }

    public function failed(): static
    {
        return $this->state(['status' => RecordStatus::Error]);
    }
}
