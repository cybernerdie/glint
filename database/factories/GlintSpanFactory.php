<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Database\Factories;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Enums\SpanType;
use Cybernerdie\Glint\Models\GlintSpan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GlintSpan>
 */
final class GlintSpanFactory extends Factory
{
    protected $model = GlintSpan::class;

    public function definition(): array
    {
        return [
            'trace_id' => Str::ulid()->toString(),
            'name' => 'Test Span',
            'type' => SpanType::Span,
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
