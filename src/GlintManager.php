<?php

declare(strict_types=1);

namespace Cybernerdie\Glint;

use Cybernerdie\Glint\Concerns\ProtectsWrites;
use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Contracts\GenerationInterface;
use Cybernerdie\Glint\Contracts\GlintClientInterface;
use Cybernerdie\Glint\Contracts\SpanInterface;
use Cybernerdie\Glint\Contracts\TraceInterface;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Enums\SpanType;
use Cybernerdie\Glint\Filtering\FilterEntry;
use Cybernerdie\Glint\Filtering\GlintFilterRegistry;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Null\NullGeneration;
use Cybernerdie\Glint\Null\NullSpan;
use Cybernerdie\Glint\Null\NullTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Testing\GlintFake;
use Cybernerdie\Glint\Tracing\ActiveGeneration;
use Cybernerdie\Glint\Tracing\ActiveSpan;
use Cybernerdie\Glint\Tracing\ActiveTrace;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;

final class GlintManager implements GlintClientInterface
{
    use Macroable;
    use ProtectsWrites;

    public function __construct(
        private readonly TraceContext $context,
        private readonly PricingRegistry $pricing,
    ) {}

    public static function fake(): GlintFake
    {
        return GlintFake::swap();
    }

    /**
     * Register a callback that decides whether an LLM call should be recorded.
     * Return false from the callback to skip recording.
     *
     * Filters live in a GlintFilterRegistry singleton so they persist across
     * Octane requests even though GlintManager itself is scoped.
     *
     * @param  callable(FilterEntry): bool  $callback
     */
    public static function filter(callable $callback): void
    {
        app(GlintFilterRegistry::class)->push($callback);
    }

    /**
     * @internal Used by GlintRecorder to test registered filters.
     *           Not part of the public API — subject to change without notice.
     */
    public static function shouldRecord(FilterEntry $entry): bool
    {
        return app(GlintFilterRegistry::class)->shouldRecord($entry);
    }

    /**
     * @internal Reset all registered filters.
     *           Called by GlintFake::swap() and test tear-down helpers.
     *           Not part of the public API — subject to change without notice.
     */
    public static function flushFilters(): void
    {
        app(GlintFilterRegistry::class)->flush();
    }

    public function isEnabled(): bool
    {
        return Config::boolean('glint.enabled', false);
    }

    /** @param array<string, mixed> $metadata */
    public function trace(string $name, array $metadata = []): TraceInterface
    {
        if (! $this->isEnabled()) {
            return new NullTrace;
        }

        $traceId = (string) Str::ulid();
        $startedAt = now();

        $this->context->openTrace($traceId, true);

        $this->protectedWrite(fn () => GlintTrace::create([
            'id' => $traceId,
            'name' => $name,
            'metadata' => $metadata,
            'status' => RecordStatus::Pending,
            'started_at' => $startedAt,
        ]));

        return new ActiveTrace($traceId, $this->context, $startedAt, $this->pricing);
    }

    /** @param array<string, mixed> $metadata */
    public function span(string $name, array $metadata = []): SpanInterface
    {
        if (! $this->isEnabled() || ! $this->context->isSampled()) {
            return new NullSpan;
        }

        $spanId = (string) Str::ulid();
        $traceId = $this->context->traceId() ?? $this->createHeadlessTrace($name);
        $startedAt = now();

        $this->protectedWrite(fn () => GlintSpan::create([
            'id' => $spanId,
            'trace_id' => $traceId,
            'name' => $name,
            'type' => SpanType::Span,
            'metadata' => $metadata,
            'status' => RecordStatus::Pending,
            'started_at' => $startedAt,
        ]));

        return new ActiveSpan($spanId, $startedAt);
    }

    /** @param array<string, mixed> $metadata */
    public function generation(string $name, string $provider, string $model, array $metadata = []): GenerationInterface
    {
        if (! $this->isEnabled() || ! $this->context->isSampled()) {
            return new NullGeneration;
        }

        $generationId = (string) Str::ulid();
        $traceId = $this->context->traceId() ?? $this->createHeadlessTrace($name);
        $startedAt = now();

        $this->protectedWrite(fn () => GlintGeneration::create([
            'id' => $generationId,
            'trace_id' => $traceId,
            'name' => $name,
            'provider' => $provider,
            'model' => $model,
            'metadata' => $metadata,
            'status' => RecordStatus::Pending,
            'started_at' => $startedAt,
        ]));

        return new ActiveGeneration($generationId, $this->pricing, $provider, $model, $startedAt);
    }

    /**
     * Create a headless trace so a manually-created span or generation never
     * references a trace_id that has no corresponding row.
     */
    private function createHeadlessTrace(string $name): string
    {
        $traceId = (string) Str::ulid();

        $this->protectedWrite(fn () => GlintTrace::create([
            'id' => $traceId,
            'name' => 'auto:'.$name,
            'status' => RecordStatus::Pending,
            'started_at' => now(),
        ]));

        return $traceId;
    }
}
