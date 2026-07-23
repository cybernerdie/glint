<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Recorders;

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Enums\SpanType;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Filtering\FilterEntry;
use Cybernerdie\Glint\Filtering\GlintFilterRegistry;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Support\Redactor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class GlintRecorder
{
    private const DedupeKeyMetadata = 'glint_dedupe_key';

    private const AliasCachePrefix = 'glint:generation-alias:';

    public function __construct(
        private TraceContext $context,
        private PricingRegistry $pricing,
        private GlintFilterRegistry $filters,
        private Redactor $redactor,
    ) {}

    public function handleLlmCallStarted(LlmCallStarted $event): void
    {
        $this->safeWrite('LlmCallStarted', function () use ($event) {
            $traceId = $event->traceId ?? $this->context->traceId();

            if (! $this->filters->shouldRecord(new FilterEntry(
                provider: $event->provider,
                model: $event->model,
                traceId: $traceId,
                metadata: $event->metadata,
            ))) {
                return;
            }

            $dedupeKey = $this->dedupeKeyFromMetadata($event->metadata);

            if ($dedupeKey !== null) {
                $duplicate = GlintGeneration::query()
                    ->where('dedupe_key', $dedupeKey)
                    ->where('status', RecordStatus::Pending)
                    ->where('started_at', '>=', now()->subSeconds(30))
                    ->first();

                if ($duplicate !== null) {
                    Cache::put(self::AliasCachePrefix.$event->generationId, $duplicate->id, now()->addMinutes(10));
                    $this->context->registerGeneration($event->generationId, $duplicate->trace_id);

                    return;
                }
            }

            if ($traceId === null) {
                $traceId = (string) Str::ulid();

                GlintTrace::create([
                    'id' => $traceId,
                    'name' => 'auto:'.$event->provider.'/'.$event->model,
                    'metadata' => ['glint_auto_trace' => true],
                    'status' => RecordStatus::Pending,
                    'started_at' => now(),
                ]);

                $this->context->registerAutoTrace($event->generationId, $traceId);
            }

            GlintGeneration::firstOrCreate(
                ['id' => $event->generationId],
                [
                    'trace_id' => $traceId,
                    'parent_span_id' => $event->parentSpanId,
                    'name' => $event->name,
                    'provider' => $event->provider,
                    'model' => $event->model,
                    'dedupe_key' => $dedupeKey,
                    'prompt' => $event->messages === null ? null : $this->redactor->metadata($event->messages),
                    'temperature' => $event->temperature,
                    'max_tokens' => $event->maxTokens,
                    'top_p' => $event->topP,
                    'is_streaming' => $event->isStreaming,
                    'metadata' => $this->redactedMetadata($event->metadata),
                    'status' => RecordStatus::Pending,
                    'started_at' => now(),
                ]
            );

            $this->context->registerGeneration($event->generationId, $traceId);
        });
    }

    public function handleLlmCallFinished(LlmCallFinished $event): void
    {
        $this->safeWrite('LlmCallFinished', function () use ($event) {
            $generation = $this->findGenerationForTerminalEvent($event->generationId);

            if ($generation === null) {
                return;
            }

            if ($generation->status !== RecordStatus::Pending) {
                return;
            }

            $costUsd = $this->pricing->costFor(
                $generation->provider,
                $generation->model,
                $event->promptTokens,
                $event->completionTokens,
            );

            $generation->update([
                'completion' => $this->truncate($this->redactor->string($event->completion)),
                'prompt_tokens' => $event->promptTokens,
                'completion_tokens' => $event->completionTokens,
                'total_tokens' => $event->promptTokens + $event->completionTokens,
                'cost_usd' => $costUsd,
                'finish_reason' => $event->finishReason,
                'duration_ms' => $event->durationMs,
                'status' => RecordStatus::Success,
                'ended_at' => now(),
            ]);

            $this->upsertAggregate($generation);
            $this->closeAutoTrace($event->generationId, RecordStatus::Success, $generation);
        });
    }

    public function handleLlmCallFailed(LlmCallFailed $event): void
    {
        $this->safeWrite('LlmCallFailed', function () use ($event) {
            $generation = $this->findGenerationForTerminalEvent($event->generationId);

            if ($generation === null) {
                return;
            }

            if ($generation->status !== RecordStatus::Pending) {
                return;
            }

            $generation->update([
                'status' => RecordStatus::Error,
                'error_message' => $this->truncate($this->redactor->string($event->errorMessage)),
                'duration_ms' => $event->durationMs,
                'ended_at' => now(),
            ]);

            $this->upsertAggregate($generation);
            $this->closeAutoTrace($event->generationId, RecordStatus::Error, $generation);
        });
    }

    public function handleLlmToolCalled(LlmToolCalled $event): void
    {
        $this->safeWrite('LlmToolCalled', function () use ($event) {
            GlintSpan::firstOrCreate(
                ['id' => $event->spanId],
                [
                    'trace_id' => $event->traceId,
                    'parent_span_id' => $event->parentSpanId,
                    'name' => $event->toolName,
                    'type' => SpanType::ToolCall,
                    'input' => json_encode($this->redactor->metadata($event->arguments)),
                    'output' => json_encode($this->redactor->value($event->result)),
                    'metadata' => $this->redactedMetadata($event->metadata),
                    'status' => RecordStatus::Success,
                    'duration_ms' => $event->durationMs,
                    'started_at' => now()->subMilliseconds($event->durationMs),
                    'ended_at' => now(),
                ]
            );
        });
    }

    private function closeAutoTrace(string $generationId, RecordStatus $status, ?GlintGeneration $generation = null): void
    {
        $traceId = $this->context->autoTraceIdForGeneration($generationId);

        if ($traceId === null) {
            $traceId = $this->autoTraceIdFromGeneration($generation);
        }

        if ($traceId === null) {
            return;
        }

        $trace = GlintTrace::select(['id', 'started_at'])->find($traceId);

        if ($trace === null) {
            $this->context->clearAutoTrace($generationId);

            return;
        }

        $endedAt = now();
        $durationMs = (int) $trace->started_at->diffInMilliseconds($endedAt);

        $trace->update([
            'status' => $status,
            'duration_ms' => $durationMs,
            'ended_at' => $endedAt,
        ]);

        $this->context->clearAutoTrace($generationId);
    }

    private function findGenerationForTerminalEvent(string $generationId): ?GlintGeneration
    {
        $generation = GlintGeneration::where('id', $generationId)->first();

        if ($generation !== null) {
            return $generation;
        }

        $canonicalGenerationId = Cache::get(self::AliasCachePrefix.$generationId);

        if (! is_string($canonicalGenerationId) || $canonicalGenerationId === '') {
            return null;
        }

        return GlintGeneration::where('id', $canonicalGenerationId)->first();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function dedupeKeyFromMetadata(array $metadata): ?string
    {
        $dedupeKey = $metadata[self::DedupeKeyMetadata] ?? null;

        return is_string($dedupeKey) && $dedupeKey !== '' ? $dedupeKey : null;
    }

    private function autoTraceIdFromGeneration(?GlintGeneration $generation): ?string
    {
        if ($generation === null) {
            return null;
        }

        $trace = GlintTrace::select(['id', 'metadata'])
            ->where('id', $generation->trace_id)
            ->first();

        if ($trace === null) {
            return null;
        }

        /** @var array<string, mixed> $metadata */
        $metadata = (array) ($trace->metadata ?? []);

        return ($metadata['glint_auto_trace'] ?? false) === true ? $trace->id : null;
    }

    private function upsertAggregate(GlintGeneration $generation): void
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
                $this->upsertAggregateBucket(
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

    private function upsertAggregateBucket(
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

    private function truncate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $limit = Config::integer('glint.recording.max_completion_chars', 65535);

        if ($limit <= 0 || mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>|null
     */
    private function redactedMetadata(array $metadata): ?array
    {
        if ($metadata === []) {
            return null;
        }

        /** @var array<string, mixed> $redacted */
        $redacted = $this->redactor->metadata($metadata) ?? [];

        return $redacted;
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
