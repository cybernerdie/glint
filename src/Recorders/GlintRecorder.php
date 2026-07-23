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
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class GlintRecorder
{
    public function __construct(
        private readonly TraceContext $context,
        private readonly PricingRegistry $pricing,
        private readonly GlintFilterRegistry $filters,
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

            if ($traceId === null) {
                // No active trace context (e.g. background job, Artisan command).
                // Auto-create a headless trace so the generation is still recorded.
                $traceId = (string) Str::ulid();

                GlintTrace::create([
                    'id' => $traceId,
                    'name' => 'auto:'.$event->provider.'/'.$event->model,
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
                    'prompt' => $event->messages,
                    'temperature' => $event->temperature,
                    'max_tokens' => $event->maxTokens,
                    'is_streaming' => $event->isStreaming,
                    'metadata' => $event->metadata ?: null,
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
            $generation = GlintGeneration::where('id', $event->generationId)->first();

            if ($generation === null) {
                return;
            }

            $costUsd = $this->pricing->costFor(
                $generation->provider,
                $generation->model,
                $event->promptTokens,
                $event->completionTokens,
            );

            $generation->update([
                'completion' => $this->truncate($event->completion),
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
            $this->closeAutoTrace($event->generationId, RecordStatus::Success);
        });
    }

    public function handleLlmCallFailed(LlmCallFailed $event): void
    {
        $this->safeWrite('LlmCallFailed', function () use ($event) {
            GlintGeneration::where('id', $event->generationId)->update([
                'status' => RecordStatus::Error,
                'error_message' => $this->truncate($event->errorMessage),
                'duration_ms' => $event->durationMs,
                'ended_at' => now(),
            ]);

            $this->closeAutoTrace($event->generationId, RecordStatus::Error);
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
                    'input' => json_encode($event->arguments),
                    'output' => json_encode($event->result),
                    'metadata' => $event->metadata ?: null,
                    'status' => RecordStatus::Success,
                    'duration_ms' => $event->durationMs,
                    'started_at' => now()->subMilliseconds($event->durationMs),
                    'ended_at' => now(),
                ]
            );
        });
    }

    private function closeAutoTrace(string $generationId, RecordStatus $status): void
    {
        $traceId = $this->context->autoTraceIdForGeneration($generationId);

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

    private function upsertAggregate(GlintGeneration $generation): void
    {
        $this->safeWrite('upsertAggregate', function () use ($generation): void {
            $durationMs = (int) $generation->duration_ms;
            $totalTokens = (int) $generation->total_tokens;
            $promptTokens = (int) $generation->prompt_tokens;
            $completionTokens = (int) $generation->completion_tokens;
            // number_format ensures a locale-independent decimal point and prevents
            // float-to-string issues (e.g. "1,5" on non-English locales) in DB::raw().
            $costUsd = number_format((float) $generation->cost_usd, 8, '.', '');

            $periodAts = [
                AggregatePeriod::Hour->value => now()->startOfHour()->toDateTimeString(),
                AggregatePeriod::Day->value => now()->startOfDay()->toDateTimeString(),
                AggregatePeriod::Week->value => now()->startOfWeek()->toDateTimeString(),
                AggregatePeriod::Month->value => now()->startOfMonth()->toDateTimeString(),
            ];

            foreach ($periodAts as $period => $periodAt) {
                // A UNIQUE index treats NULLs as distinct, so insertOrIgnore would
                // never dedupe the global (user_id/team_id NULL) rows. Check for an
                // existing row explicitly so the running totals accumulate correctly.
                $exists = DB::table('glint_aggregates')
                    ->where('period', $period)
                    ->where('period_at', $periodAt)
                    ->where('provider', $generation->provider)
                    ->where('model', $generation->model)
                    ->whereNull('user_id')
                    ->whereNull('team_id')
                    ->exists();

                if (! $exists) {
                    DB::table('glint_aggregates')->insert([
                        'period' => $period,
                        'period_at' => $periodAt,
                        'provider' => $generation->provider,
                        'model' => $generation->model,
                        'user_id' => null,
                        'team_id' => null,
                        'total_requests' => 1,
                        'successful_requests' => 1,
                        'failed_requests' => 0,
                        'total_tokens' => $totalTokens,
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'total_cost_usd' => (float) $costUsd,
                        'avg_duration_ms' => $durationMs,
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ]);
                } else {
                    DB::update(
                        <<<'SQL'
                        UPDATE glint_aggregates
                        SET total_requests     = total_requests + 1,
                            successful_requests = successful_requests + 1,
                            total_tokens       = total_tokens + ?,
                            prompt_tokens      = prompt_tokens + ?,
                            completion_tokens  = completion_tokens + ?,
                            total_cost_usd     = total_cost_usd + ?,
                            avg_duration_ms    = (COALESCE(avg_duration_ms, 0) * total_requests + ?) / (total_requests + 1),
                            updated_at         = ?
                        WHERE period = ? AND period_at = ? AND provider = ? AND model = ?
                          AND user_id IS NULL AND team_id IS NULL
                        SQL,
                        [
                            $totalTokens,
                            $promptTokens,
                            $completionTokens,
                            (float) $costUsd,
                            $durationMs,
                            now()->toDateTimeString(),
                            $period,
                            $periodAt,
                            $generation->provider,
                            $generation->model,
                        ]
                    );
                }
            }
        });
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

    private function safeWrite(string $context, callable $callback): void
    {
        if (Config::boolean('glint.throw_on_exceptions', false)) {
            $callback();

            return;
        }

        rescue($callback, fn (\Throwable $e) => logger()->warning("[Glint] Failed to record {$context}: ".$e->getMessage()));
    }
}
