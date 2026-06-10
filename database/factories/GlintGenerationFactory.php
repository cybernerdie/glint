<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Database\Factories;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GlintGeneration>
 */
final class GlintGenerationFactory extends Factory
{
    protected $model = GlintGeneration::class;

    public function definition(): array
    {
        return [
            'trace_id' => (string) Str::uuid(),
            'name' => 'Test Generation',
            'provider' => 'openai',
            'model' => 'gpt-4o',
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

    public function forProvider(string $provider, string $model): static
    {
        return $this->state(['provider' => $provider, 'model' => $model]);
    }
}
