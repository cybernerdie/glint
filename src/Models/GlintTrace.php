<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Models;

use Cybernerdie\Glint\Contracts\HasPrunable;
use Cybernerdie\Glint\Database\Factories\GlintTraceFactory;
use Cybernerdie\Glint\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property string $id
 * @property string|null $name
 * @property string|null $user_id
 * @property string|null $session_id
 * @property RecordStatus $status
 * @property array<string, mixed>|null $metadata
 * @property string|null $output
 * @property int|null $duration_ms
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 *
 * @implements HasPrunable<GlintTrace>
 */
class GlintTrace extends Model implements HasPrunable
{
    /** @use HasFactory<GlintTraceFactory> */
    use HasFactory;

    use HasUlids;
    use MassPrunable;

    protected static function newFactory(): GlintTraceFactory
    {
        return GlintTraceFactory::new();
    }

    protected $table = 'glint_traces';

    protected $fillable = [
        'id', 'name', 'user_id', 'session_id', 'team_id',
        'metadata', 'input', 'output', 'status',
        'duration_ms', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'metadata' => 'array',
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

    /** @return HasMany<GlintGeneration, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(GlintGeneration::class, 'trace_id', 'id');
    }

    /** @return HasMany<GlintSpan, $this> */
    public function spans(): HasMany
    {
        return $this->hasMany(GlintSpan::class, 'trace_id', 'id');
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
}
