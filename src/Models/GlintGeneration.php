<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Models;

use Cybernerdie\Glint\Contracts\HasPrunable;
use Cybernerdie\Glint\Database\Factories\GlintGenerationFactory;
use Cybernerdie\Glint\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property string $id
 * @property string $trace_id
 * @property string|null $parent_span_id
 * @property string|null $name
 * @property string $provider
 * @property string $model
 * @property array<int, mixed>|null $prompt
 * @property string|null $completion
 * @property RecordStatus $status
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $total_tokens
 * @property string|null $cost_usd
 * @property string|null $finish_reason
 * @property string|null $error_message
 * @property int|null $duration_ms
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 *
 * @implements HasPrunable<GlintGeneration>
 */
class GlintGeneration extends Model implements HasPrunable
{
    /** @use HasFactory<GlintGenerationFactory> */
    use HasFactory;

    use HasUlids;
    use MassPrunable;

    protected static function newFactory(): GlintGenerationFactory
    {
        return GlintGenerationFactory::new();
    }

    protected $table = 'glint_generations';

    protected $fillable = [
        'id', 'trace_id', 'parent_span_id', 'name',
        'provider', 'model', 'prompt', 'completion', 'status',
        'prompt_tokens', 'completion_tokens', 'total_tokens',
        'cost_usd', 'temperature', 'max_tokens', 'top_p',
        'finish_reason', 'is_streaming', 'error_message',
        'metadata', 'duration_ms', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'prompt' => 'array',
            'metadata' => 'array',
            'cost_usd' => 'decimal:8',
            'temperature' => 'decimal:2',
            'top_p' => 'decimal:2',
            'is_streaming' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        $days = Config::integer('glint.retention.traces_days', 30);

        return self::query()->where('started_at', '<', now()->subDays($days));
    }

    /** @return BelongsTo<GlintTrace, $this> */
    public function trace(): BelongsTo
    {
        return $this->belongsTo(GlintTrace::class, 'trace_id', 'id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Pending);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Success);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', RecordStatus::Error);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForModel(Builder $query, string $model): Builder
    {
        return $query->where('model', $model);
    }
}
