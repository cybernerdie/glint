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
        if (! $this->context->isSampled()) {
            return;
        }

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
        // Note: isSampled() is intentionally NOT checked here. The Finished/Failed
        // events carry a generationId that was already written by handleLlmCallStarted.
        // If sampling rejected the call at Start time, no DB row exists and the
        // GlintGeneration::where() lookup simply returns null — safe and correct.
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
                'completion' => $event->completion,
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
        // Note: isSampled() is intentionally NOT checked here — see handleLlmCallFinished.
        $this->safeWrite('LlmCallFailed', function () use ($event) {
            GlintGeneration::where('id', $event->generationId)->update([
                'status' => RecordStatus::Error,
                'error_message' => $event->errorMessage,
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

        $trace = GlintTrace::find($traceId);

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
        // One atomic upsert per period bucket. Writing all four periods (hour/day/week/month)
        // on every finished generation ensures alert rules and cost queries can use any
        // period granularity without missing data.
        // NOTE: For high-traffic apps, consider batching these increments via a scheduled
        // job rather than writing on every single LLM call.
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
                DB::table('glint_aggregates')->upsert(
                    [
                        [
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
                        ],
                    ],
                    // Unique key columns (must match the glint_agg_unique index)
                    ['period', 'period_at', 'provider', 'model', 'user_id', 'team_id'],
                    // Columns to increment on conflict
                    [
                        'total_requests' => DB::raw('glint_aggregates.total_requests + 1'),
                        'successful_requests' => DB::raw('glint_aggregates.successful_requests + 1'),
                        'total_tokens' => DB::raw('glint_aggregates.total_tokens + '.intval($totalTokens)),
                        'prompt_tokens' => DB::raw('glint_aggregates.prompt_tokens + '.intval($promptTokens)),
                        'completion_tokens' => DB::raw('glint_aggregates.completion_tokens + '.intval($completionTokens)),
                        'total_cost_usd' => DB::raw('glint_aggregates.total_cost_usd + '.$costUsd),
                        'avg_duration_ms' => DB::raw('(COALESCE(glint_aggregates.avg_duration_ms, 0) * glint_aggregates.total_requests + '.intval($durationMs).') / (glint_aggregates.total_requests + 1)'),
                        'updated_at' => now()->toDateTimeString(),
                    ]
                );
            }
        });
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
