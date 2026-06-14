<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Testing;

use Cybernerdie\Glint\Contracts\GenerationInterface;
use Cybernerdie\Glint\Contracts\GlintClientInterface;
use Cybernerdie\Glint\Contracts\SpanInterface;
use Cybernerdie\Glint\Contracts\TraceInterface;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\GlintManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class GlintFake implements GlintClientInterface
{
    public function __construct(
        private readonly RecordingStore $store,
    ) {}

    public static function swap(): self
    {
        GlintManager::flushFilters();

        Event::forget(LlmCallStarted::class);
        Event::forget(LlmCallFinished::class);
        Event::forget(LlmCallFailed::class);
        Event::forget(LlmToolCalled::class);

        $store = new RecordingStore;

        Event::listen(LlmCallStarted::class, [$store, 'handleLlmCallStarted']);
        Event::listen(LlmCallFinished::class, [$store, 'handleLlmCallFinished']);
        Event::listen(LlmCallFailed::class, [$store, 'handleLlmCallFailed']);
        Event::listen(LlmToolCalled::class, [$store, 'handleLlmToolCalled']);

        $fake = new self($store);

        app()->instance('glint', $fake);
        app()->instance(GlintClientInterface::class, $fake);

        return $fake;
    }

    /**
     * Restore the real GlintManager binding and flush all filters.
     * Call this in tearDown() when not relying on a fresh app per test.
     */
    public static function restore(): void
    {
        GlintManager::flushFilters();

        Event::forget(LlmCallStarted::class);
        Event::forget(LlmCallFinished::class);
        Event::forget(LlmCallFailed::class);
        Event::forget(LlmToolCalled::class);

        app()->forgetInstance('glint');
        app()->forgetInstance(GlintClientInterface::class);
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /** @param array<string, mixed> $metadata */
    public function trace(string $name, array $metadata = []): TraceInterface
    {
        return new FakeTrace($this->store);
    }

    /** @param array<string, mixed> $metadata */
    public function span(string $name, array $metadata = []): SpanInterface
    {
        $this->store->recordSpan($name);

        return new FakeSpan;
    }

    /** @param array<string, mixed> $metadata */
    public function generation(string $name, string $provider, string $model, array $metadata = []): GenerationInterface
    {
        $generationId = (string) Str::ulid();

        event(new LlmCallStarted(
            generationId: $generationId,
            provider: $provider,
            model: $model,
            messages: null,
            temperature: null,
            maxTokens: null,
            isStreaming: false,
            name: $name,
            metadata: $metadata,
        ));

        return new FakeGeneration($generationId);
    }

    public function store(): RecordingStore
    {
        return $this->store;
    }

    public function assertGenerationCount(int $expected): void
    {
        $this->store->assertGenerationCount($expected);
    }

    public function assertHasGeneration(string $provider, string $model): void
    {
        $this->store->assertHasGeneration($provider, $model);
    }

    public function assertGenerationForName(string $name): void
    {
        $this->store->assertGenerationForName($name);
    }

    public function assertHasToolCall(string $toolName): void
    {
        $this->store->assertHasToolCall($toolName);
    }

    public function assertSpanCount(int $expected): void
    {
        $this->store->assertSpanCount($expected);
    }

    public function assertHasSpan(string $name): void
    {
        $this->store->assertHasSpan($name);
    }

    public function assertNoSpans(): void
    {
        $this->store->assertNoSpans();
    }

    public function assertNothingRecorded(): void
    {
        $this->store->assertNothingRecorded();
    }

    public function assertNoGenerations(): void
    {
        $this->store->assertNoGenerations();
    }

    public function assertMissingGeneration(string $provider, string $model): void
    {
        $this->store->assertMissingGeneration($provider, $model);
    }

    public function assertToolCallCount(int $expected): void
    {
        $this->store->assertToolCallCount($expected);
    }

    public function assertNoToolCalls(): void
    {
        $this->store->assertNoToolCalls();
    }

    public function assertGenerationSucceeded(string $provider, string $model): void
    {
        $this->store->assertGenerationSucceeded($provider, $model);
    }

    public function assertGenerationFailed(string $provider, string $model): void
    {
        $this->store->assertGenerationFailed($provider, $model);
    }

    public function filter(callable $callback): void
    {
        GlintManager::filter($callback);
    }

    /** @return Collection<int, CapturedGeneration> */
    public function generations(): Collection
    {
        return $this->store->generations();
    }

    /** @return Collection<int, array{spanId: string, traceId: string, toolName: string, arguments: array<string, mixed>, result: mixed, durationMs: int}> */
    public function toolCalls(): Collection
    {
        return $this->store->toolCalls();
    }

    public function flush(): void
    {
        $this->store->flush();
    }
}
