<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Aggregates;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

final class GenerationAggregateRecorder
{
    public function record(GlintGeneration $generation): void
    {
        $this->safeWrite('upsertAggregate', function () use ($generation): void {
            $durationMs = (int) $generation->duration_ms;
            $totalTokens = (int) $generation->total_tokens;
            $promptTokens = (int) $generation->prompt_tokens;
            $completionTokens = (int) $generation->completion_tokens;
            $costUsd = number_format((float) $generation->cost_usd, 8, '.', '');

            $periodAts = [
                AggregatePeriod::Hour->value => now()->startOfHour()->toDateTimeString(),
                AggregatePeriod::Day->value => now()->startOfDay()->toDateTimeString(),
                AggregatePeriod::Week->value => now()->startOfWeek()->toDateTimeString(),
                AggregatePeriod::Month->value => now()->startOfMonth()->toDateTimeString(),
            ];

            foreach ($periodAts as $period => $periodAt) {
                $this->recordBucket(
                    period: $period,
                    periodAt: $periodAt,
                    provider: $generation->provider,
                    model: $generation->model,
                    status: $generation->status,
                    totalTokens: $totalTokens,
                    promptTokens: $promptTokens,
                    completionTokens: $completionTokens,
                    costUsd: (float) $costUsd,
                    durationMs: $durationMs,
                );
            }
        });
    }

    private function recordBucket(
        string $period,
        string $periodAt,
        string $provider,
        string $model,
        RecordStatus $status,
        int $totalTokens,
        int $promptTokens,
        int $completionTokens,
        float $costUsd,
        int $durationMs,
    ): void {
        $now = now()->toDateTimeString();
        $dimension = GlintAggregate::GlobalDimension;
        $successfulRequests = $status === RecordStatus::Success ? 1 : 0;
        $failedRequests = $status === RecordStatus::Error ? 1 : 0;

        DB::table('glint_aggregates')->insertOrIgnore([
            'period' => $period,
            'period_at' => $periodAt,
            'provider' => $provider,
            'model' => $model,
            'user_id' => $dimension,
            'team_id' => $dimension,
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_tokens' => 0,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_cost_usd' => 0,
            'avg_duration_ms' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::update(
            <<<'SQL'
            UPDATE glint_aggregates
            SET avg_duration_ms    = (COALESCE(avg_duration_ms, 0) * total_requests + ?) / (total_requests + 1),
                total_requests     = total_requests + 1,
                successful_requests = successful_requests + ?,
                failed_requests     = failed_requests + ?,
                total_tokens       = total_tokens + ?,
                prompt_tokens      = prompt_tokens + ?,
                completion_tokens  = completion_tokens + ?,
                total_cost_usd     = total_cost_usd + ?,
                updated_at         = ?
            WHERE period = ? AND period_at = ? AND provider = ? AND model = ?
              AND user_id = ? AND team_id = ?
            SQL,
            [
                $durationMs,
                $successfulRequests,
                $failedRequests,
                $totalTokens,
                $promptTokens,
                $completionTokens,
                $costUsd,
                $now,
                $period,
                $periodAt,
                $provider,
                $model,
                $dimension,
                $dimension,
            ]
        );
    }

    private function safeWrite(string $context, callable $callback): void
    {
        if (Config::boolean('glint.throw_on_exceptions', false)) {
            $callback();

            return;
        }

        rescue($callback, fn (\Throwable $e) => logger()->warning("[Glint] Failed to record {$context}: ".$e->getMessage()));
    }
}
