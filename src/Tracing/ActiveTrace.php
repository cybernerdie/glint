<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Tracing;

use Carbon\Carbon;
use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Contracts\TraceInterface;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Enums\SpanType;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ActiveTrace implements TraceInterface
{
    public function __construct(
        private readonly string $traceId,
        private readonly TraceContext $context,
        private readonly Carbon $startedAt,
        private readonly PricingRegistry $pricing,
    ) {}

    public function span(string $name, callable $callback): mixed
    {
        $spanId = (string) Str::ulid();
        $spanStartedAt = now();

        rescue(fn () => GlintSpan::create([
            'id' => $spanId,
            'trace_id' => $this->traceId,
            'name' => $name,
            'type' => SpanType::Span,
            'status' => RecordStatus::Pending,
            'started_at' => $spanStartedAt,
        ]));

        $span = new ActiveSpan($spanId, $spanStartedAt);

        $thrown = null;
        $result = null;

        try {
            $result = $callback($span);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        if ($thrown !== null) {
            rescue(fn () => GlintSpan::where('id', $spanId)->update([
                'status' => RecordStatus::Error,
                'ended_at' => now(),
                'duration_ms' => (int) $spanStartedAt->diffInMilliseconds(now()),
            ]));
            throw $thrown;
        }

        $span->end();

        return $result;
    }

    public function generation(string $name, callable $callback, string $provider = '', string $model = ''): mixed
    {
        $spanId = (string) Str::ulid();
        $genStartedAt = now();

        rescue(fn () => GlintSpan::create([
            'id' => $spanId,
            'trace_id' => $this->traceId,
            'name' => $name,
            'type' => SpanType::Generation,
            'status' => RecordStatus::Pending,
            'started_at' => $genStartedAt,
        ]));

        $generationId = (string) Str::ulid();

        rescue(fn () => GlintGeneration::create([
            'id' => $generationId,
            'trace_id' => $this->traceId,
            'parent_span_id' => $spanId,
            'name' => $name,
            'provider' => $provider,
            'model' => $model,
            'status' => RecordStatus::Pending,
            'started_at' => $genStartedAt,
        ]));

        $generation = new ActiveGeneration($generationId, $this->pricing, $provider, $model, $genStartedAt);

        $thrown = null;
        $result = null;

        try {
            $result = $callback($generation);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $endedAt = now();
        $durationMs = (int) $genStartedAt->diffInMilliseconds($endedAt);
        $status = $thrown === null ? RecordStatus::Success : RecordStatus::Error;

        rescue(fn () => GlintSpan::where('id', $spanId)->update([
            'status' => $status,
            'ended_at' => $endedAt,
            'duration_ms' => $durationMs,
        ]));

        // Only update the generation if the callback threw — in that case the
        // generation was never finished/failed by the callback itself, so we
        // need to mark it as an error here. On the success path the callback
        // is responsible for calling $generation->finish(), so we must NOT
        // overwrite whatever status it already set.
        if ($thrown !== null) {
            rescue(fn () => GlintGeneration::where('id', $generationId)->update([
                'status' => RecordStatus::Error,
                'ended_at' => $endedAt,
                'duration_ms' => $durationMs,
            ]));

            throw $thrown;
        }

        return $result;
    }

    /**
     * Add a key/value tag to the trace's metadata.
     *
     * Each call issues a SELECT + UPDATE against glint_traces. For traces
     * that receive many tags, batch them before calling tag() or accumulate
     * them in the metadata array passed to Glint::trace().
     */
    public function tag(string $key, string $value): static
    {
        rescue(function () use ($key, $value): void {
            DB::transaction(function () use ($key, $value): void {
                $trace = GlintTrace::where('id', $this->traceId)->lockForUpdate()->first();

                if ($trace === null) {
                    return;
                }

                /** @var array<string, mixed> $metadata */
                $metadata = (array) ($trace->metadata ?? []);
                /** @var array<string, mixed> $tags */
                $tags = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
                $tags[$key] = $value;
                $metadata['tags'] = $tags;

                $trace->update(['metadata' => $metadata]);
            });
        });

        return $this;
    }

    /**
     * Write multiple key/value tags in a single atomic transaction.
     * Prefer this over calling tag() in a loop when you have several tags.
     *
     * @param  array<string, string>  $tags
     */
    public function tags(array $tags): static
    {
        if ($tags === []) {
            return $this;
        }

        rescue(function () use ($tags): void {
            DB::transaction(function () use ($tags): void {
                $trace = GlintTrace::where('id', $this->traceId)->lockForUpdate()->first();

                if ($trace === null) {
                    return;
                }

                /** @var array<string, mixed> $metadata */
                $metadata = (array) ($trace->metadata ?? []);
                /** @var array<string, mixed> $existing */
                $existing = is_array($metadata['tags'] ?? null) ? $metadata['tags'] : [];
                $metadata['tags'] = array_merge($existing, $tags);

                $trace->update(['metadata' => $metadata]);
            });
        });

        return $this;
    }

    public function end(): void
    {
        rescue(function (): void {
            $now = now();
            GlintTrace::where('id', $this->traceId)->update([
                'status' => RecordStatus::Success,
                'ended_at' => $now,
                'duration_ms' => (int) $this->startedAt->diffInMilliseconds($now),
            ]);
        });

        $this->context->closeTrace();
    }

    public function traceId(): string
    {
        return $this->traceId;
    }
}
