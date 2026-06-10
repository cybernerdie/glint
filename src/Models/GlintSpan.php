<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Models;

use Cybernerdie\Glint\Contracts\HasPrunable;
use Cybernerdie\Glint\Database\Factories\GlintSpanFactory;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Enums\SpanType;
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
 * @property string $name
 * @property SpanType $type
 * @property RecordStatus $status
 * @property string|null $input
 * @property string|null $output
 * @property array<string, mixed>|null $metadata
 * @property int|null $duration_ms
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 *
 * @implements HasPrunable<GlintSpan>
 */
class GlintSpan extends Model implements HasPrunable
{
    /** @use HasFactory<GlintSpanFactory> */
    use HasFactory;

    use HasUlids;
    use MassPrunable;

    protected static function newFactory(): GlintSpanFactory
    {
        return GlintSpanFactory::new();
    }

    protected $table = 'glint_spans';

    protected $fillable = [
        'id', 'trace_id', 'parent_span_id', 'name', 'type',
        'status', 'input', 'output', 'metadata',
        'duration_ms', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => SpanType::class,
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

    /** @return BelongsTo<GlintTrace, $this> */
    public function trace(): BelongsTo
    {
        return $this->belongsTo(GlintTrace::class, 'trace_id', 'id');
    }
}
