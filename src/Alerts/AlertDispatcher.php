<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Alerts;

use Cybernerdie\Glint\Enums\AlertEventStatus;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintAlertEvent;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class AlertDispatcher
{
    public function evaluate(): void
    {
        $rules = GlintAlertRule::query()->where('enabled', true)->get();

        if ($rules->isEmpty()) {
            return;
        }

        $aggregateCache = $this->buildAggregateCache($rules);

        foreach ($rules as $rule) {
            rescue(fn () => $this->evaluateRule($rule, $aggregateCache));
        }
    }

    /**
     * @param  Collection<int, GlintAlertRule>  $rules
     * @return Collection<string, GlintAggregate>
     */
    private function buildAggregateCache(Collection $rules): Collection
    {
        $pairs = $rules
            ->filter(fn (GlintAlertRule $r) => ! $r->isWithinCooldown())
            ->map(function (GlintAlertRule $rule): array {
                $config = $rule->threshold_config ?? [];

                return [
                    'period' => is_string($config['period'] ?? null) ? $config['period'] : 'day',
                    'provider' => is_string($config['provider'] ?? null) ? $config['provider'] : null,
                ];
            })
            ->unique(fn (array $p) => $p['period'].'|'.($p['provider'] ?? ''))
            ->values();

        $data = [];

        foreach ($pairs as $pair) {
            $query = GlintAggregate::query()->where('period', $pair['period']);

            if ($pair['provider'] !== null) {
                $query->where('provider', $pair['provider']);
            }

            $aggregate = $query->orderByDesc('period_at')->first();

            if ($aggregate !== null) {
                $cacheKey = $pair['period'].'|'.($pair['provider'] ?? '');
                $data[$cacheKey] = $aggregate;
            }
        }

        return collect($data);
    }

    /**
     * @param  Collection<string, GlintAggregate>  $aggregateCache
     */
    private function evaluateRule(GlintAlertRule $rule, Collection $aggregateCache): void
    {
        if ($rule->isWithinCooldown()) {
            return;
        }

        $config = $rule->threshold_config ?? [];
        $period = is_string($config['period'] ?? null) ? $config['period'] : 'day';
        $provider = is_string($config['provider'] ?? null) ? $config['provider'] : null;
        $thresholdRaw = $config['threshold'] ?? null;
        $threshold = is_numeric($thresholdRaw) ? (float) $thresholdRaw : null;

        if ($threshold === null) {
            return;
        }

        $cacheKey = $period.'|'.($provider ?? '');
        $aggregate = $aggregateCache->get($cacheKey);

        if ($aggregate === null) {
            return;
        }

        $currentValue = match ($rule->type) {
            AlertRuleType::CostThreshold => (float) $aggregate->total_cost_usd,
            AlertRuleType::TokenSpike => (float) ($aggregate->prompt_tokens + $aggregate->completion_tokens),
            AlertRuleType::ErrorRate => $aggregate->total_requests > 0
                ? ($aggregate->failed_requests / $aggregate->total_requests) * 100
                : 0.0,
            AlertRuleType::LatencySpike => (float) ($aggregate->avg_duration_ms ?? 0),
        };

        if ($currentValue >= $threshold) {
            $channels = $rule->channels ?? [];
            $channel = count($channels) > 0 ? (string) $channels[0] : 'log';

            DB::transaction(function () use ($rule, $threshold, $currentValue, $period, $channel): void {
                $alertEvent = GlintAlertEvent::create([
                    'alert_rule_id' => $rule->id,
                    'triggered_at' => now(),
                    'channel' => $channel,
                    'status' => AlertEventStatus::Sent,
                    'context' => [
                        'type' => $rule->type->value,
                        'threshold' => $threshold,
                        'current_value' => $currentValue,
                        'period' => $period,
                    ],
                ]);

                $rule->update(['last_triggered_at' => now()]);

                event(new GlintAlertTriggered(
                    alertRuleId: (int) $rule->id,
                    type: $rule->type,
                    threshold: $threshold,
                    currentValue: $currentValue,
                    period: $period,
                    channel: $channel,
                    alertEventId: (int) $alertEvent->id,
                ));
            });
        }
    }
}
