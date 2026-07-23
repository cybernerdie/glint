<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Models;

use Cybernerdie\Glint\Contracts\HasPrunable;
use Cybernerdie\Glint\Database\Factories\GlintAggregateFactory;
use Cybernerdie\Glint\Enums\AggregatePeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 * @property AggregatePeriod $period
 * @property Carbon $period_at
 * @property string $provider
 * @property string $model
 * @property string|null $user_id
 * @property string|null $team_id
 * @property int $total_requests
 * @property int $successful_requests
 * @property int $failed_requests
 * @property int $total_tokens
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property numeric-string $total_cost_usd
 * @property int|null $avg_duration_ms
 * @property int|null $p95_duration_ms
 * @property int|null $p99_duration_ms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @implements HasPrunable<GlintAggregate>
 */
class GlintAggregate extends Model implements HasPrunable
{
    /** @use HasFactory<GlintAggregateFactory> */
    use HasFactory;

    use MassPrunable;

    protected static function newFactory(): GlintAggregateFactory
    {
        return GlintAggregateFactory::new();
    }

    protected $table = 'glint_aggregates';

    protected $fillable = [
        'period', 'period_at', 'provider', 'model', 'user_id', 'team_id',
        'total_requests', 'successful_requests', 'failed_requests',
        'total_tokens', 'prompt_tokens', 'completion_tokens',
        'total_cost_usd', 'avg_duration_ms', 'p95_duration_ms', 'p99_duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'period' => AggregatePeriod::class,
            'period_at' => 'datetime',
            'total_cost_usd' => 'decimal:6',
        ];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        $days = Config::integer('glint.retention.aggregates_days', 365);

        return self::query()->where('period_at', '<', now()->subDays($days));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPeriod(Builder $query, AggregatePeriod $period): Builder
    {
        return $query->where('period', $period);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }
}
