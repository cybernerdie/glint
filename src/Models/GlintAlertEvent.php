<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Models;

use Cybernerdie\Glint\Contracts\HasPrunable;
use Cybernerdie\Glint\Enums\AlertEventStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;

/**
 * @property AlertEventStatus $status
 * @property string $channel
 * @property array<string, mixed> $context
 *
 * @implements HasPrunable<GlintAlertEvent>
 */
class GlintAlertEvent extends Model implements HasPrunable
{
    use MassPrunable;

    protected $table = 'glint_alert_events';

    protected $fillable = [
        'alert_rule_id', 'triggered_at', 'context', 'channel', 'status', 'error',
    ];

    // Alert events are immutable once created — no UPDATE ever runs against this table.
    // Disabling updated_at saves one column write per insert.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'status' => AlertEventStatus::class,
            'context' => 'array',
            'triggered_at' => 'datetime',
        ];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        $days = Config::integer('glint.retention.alert_days', 90);

        return self::query()->where('triggered_at', '<', now()->subDays($days));
    }

    /** @return BelongsTo<GlintAlertRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(GlintAlertRule::class, 'alert_rule_id');
    }
}
