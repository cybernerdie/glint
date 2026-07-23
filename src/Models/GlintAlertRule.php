<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Models;

use Cybernerdie\Glint\Database\Factories\GlintAlertRuleFactory;
use Cybernerdie\Glint\Enums\AlertRuleScope;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property bool $enabled
 * @property AlertRuleType $type
 * @property AlertRuleScope $scope
 * @property Carbon|null $last_triggered_at
 * @property int $cooldown_minutes
 * @property array<string, mixed>|null $threshold_config
 * @property array<int, string>|null $channels
 */
class GlintAlertRule extends Model
{
    /** @use HasFactory<GlintAlertRuleFactory> */
    use HasFactory;

    protected $table = 'glint_alert_rules';

    protected $fillable = [
        'name', 'type', 'scope', 'scope_id',
        'threshold_config', 'channels', 'webhook_url', 'mail_to',
        'cooldown_minutes', 'enabled', 'last_triggered_at',
    ];

    protected static function newFactory(): GlintAlertRuleFactory
    {
        return GlintAlertRuleFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => AlertRuleType::class,
            'scope' => AlertRuleScope::class,
            'threshold_config' => 'array',
            'channels' => 'array',
            'enabled' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    /** @return HasMany<GlintAlertEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(GlintAlertEvent::class, 'alert_rule_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function isWithinCooldown(): bool
    {
        if ($this->last_triggered_at === null) {
            return false;
        }

        return $this->last_triggered_at->addMinutes($this->cooldown_minutes)->isFuture();
    }
}
