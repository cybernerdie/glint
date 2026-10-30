<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Tracing;

use Carbon\Carbon;
use Cybernerdie\Glint\Aggregates\GenerationAggregateRecorder;
use Cybernerdie\Glint\Concerns\ProtectsWrites;
use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Contracts\TraceInterface;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Enums\SpanType;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Support\Redactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ActiveTrace implements TraceInterface
{
    use ProtectsWrites;

    public function __construct(
        private readonly string $traceId,
        private readonly TraceContext $context,
        private readonly Carbon $startedAt,
        private readonly PricingRegistry $pricing,
        private readonly Redactor $redactor,
        private readonly GenerationAggregateRecorder $aggregates,
    ) {}

    public function span(string $name, callable $callback): mixed
    {
        $spanId = (string) Str::ulid();
        $spanStartedAt = now();

        $this->protectedWrite(fn () => GlintSpan::create([
            'id' => $spanId,
            'trace_id' => $this->traceId,
            'parent_span_id' => $this->context->activeSpanId(),
            'name' => $name,
            'type' => SpanType::Span,
            'status' => RecordStatus::Pending,
            'started_at' => $spanStartedAt,
        ]));

        $span = new ActiveSpan($spanId, $spanStartedAt, $this->redactor, $this->context);

        $thrown = null;
        $result = null;

        try {
            $result = $callback($span);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        if ($thrown !== null) {
            $span->fail();
            throw $thrown;
        }

        $span->end();

        return $result;
    }

    public function generation(string $name, callable $callback, string $provider = '', string $model = ''): mixed
    {
        $spanId = (string) Str::ulid();
        $genStartedAt = now();

        $this->protectedWrite(fn () => GlintSpan::create([
            'id' => $spanId,
            'trace_id' => $this->traceId,
            'parent_span_id' => $this->context->activeSpanId(),
            'name' => $name,
            'type' => SpanType::Generation,
            'status' => RecordStatus::Pending,
            'started_at' => $genStartedAt,
        ]));

        $generationId = (string) Str::ulid();

        $this->protectedWrite(fn () => GlintGeneration::create([
            'id' => $generationId,
            'trace_id' => $this->traceId,
            'parent_span_id' => $spanId,
            'name' => $name,
            'provider' => $provider,
            'model' => $model,
            'status' => RecordStatus::Pending,
            'started_at' => $genStartedAt,
        ]));

        $generation = new ActiveGeneration($generationId, $this->pricing, $provider, $model, $genStartedAt, $this->redactor, $this->aggregates);

        $thrown = null;
        $result = null;

        $this->context->openSpan($spanId);

        try {
            $result = $callback($generation);
        } catch (\Throwable $e) {
            $thrown = $e;
        } finally {
            $this->context->closeSpan($spanId);
        }

        $endedAt = now();
        $durationMs = (int) $genStartedAt->diffInMilliseconds($endedAt);
        $status = $thrown === null ? RecordStatus::Success : RecordStatus::Error;

        $this->protectedWrite(fn () => GlintSpan::where('id', $spanId)->update([
            'status' => $status,
            'ended_at' => $endedAt,
            'duration_ms' => $durationMs,
        ]));

        // Only mark error on throw — on success the callback calls $generation->finish() itself.
        if ($thrown !== null) {
            $generation->fail($thrown);

            throw $thrown;
        }

        return $result;
    }

    public function tag(string $key, string $value): static
    {
        $this->protectedWrite(function () use ($key, $value): void {
            DB::transaction(function () use ($key, $value): void {
                $trace = GlintTrace::where('id', $this->traceId)->lockForUpdate()->first();

                if ($trace === null) {
                    return;
                }

                /** @var array<string, mixed> $metadata */
                $metadata = (array) ($trace->metadata ?? []);
                /** @var array<string, mixed> $tags */
                $tags = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
                $tags[$key] = $this->redactor->string($value);
                $metadata['tags'] = $tags;

                $trace->update(['metadata' => $metadata]);
            });
        });

        return $this;
    }

    /**
     * @param  array<string, string>  $tags
     */
    public function tags(array $tags): static
    {
        if ($tags === []) {
            return $this;
        }

        $this->protectedWrite(function () use ($tags): void {
            DB::transaction(function () use ($tags): void {
                $trace = GlintTrace::where('id', $this->traceId)->lockForUpdate()->first();

                if ($trace === null) {
                    return;
                }

                /** @var array<string, mixed> $metadata */
                $metadata = (array) ($trace->metadata ?? []);
                /** @var array<string, mixed> $existing */
                $existing = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
                /** @var array<string, string> $redactedTags */
                $redactedTags = $this->redactor->metadata($tags) ?? [];
                $metadata['tags'] = array_merge($existing, $redactedTags);

                $trace->update(['metadata' => $metadata]);
            });
        });

        return $this;
    }

    public function end(): void
    {
        $this->protectedWrite(function (): void {
            $now = now();
            GlintTrace::where('id', $this->traceId)->update([
                'status' => RecordStatus::Success,
                'ended_at' => $now,
                'duration_ms' => (int) $this->startedAt->diffInMilliseconds($now),
            ]);
        });

        $this->context->closeTrace($this->traceId);
    }

    public function traceId(): string
    {
        return $this->traceId;
    }
}
