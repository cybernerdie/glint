<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Database\Factories;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Models\GlintAggregate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlintAggregate>
 */
final class GlintAggregateFactory extends Factory
{
    protected $model = GlintAggregate::class;

    public function definition(): array
    {
        return [
            'period' => AggregatePeriod::Day,
            'period_at' => now()->startOfDay(),
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'user_id' => GlintAggregate::GlobalDimension,
            'team_id' => GlintAggregate::GlobalDimension,
            'total_requests' => 10,
            'successful_requests' => 10,
            'failed_requests' => 0,
            'total_tokens' => 1000,
            'prompt_tokens' => 500,
            'completion_tokens' => 500,
            'total_cost_usd' => 1.00,
        ];
    }
}
