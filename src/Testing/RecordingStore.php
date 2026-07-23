<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Testing;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Filtering\FilterEntry;
use Cybernerdie\Glint\Filtering\GlintFilterRegistry;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;

final class RecordingStore
{
    private ?PricingRegistry $pricing = null;

    /** @var array<string, array{id: string, provider: string, model: string, name: string, status: RecordStatus, completion: null|string, promptTokens: int, completionTokens: int, costUsd: float, finishReason: null|string, errorMessage: null|string, metadata: array<string, mixed>}> */
    private array $generations = [];

    /** @var array<int, array{spanId: string, traceId: string, toolName: string, arguments: array<string, mixed>, result: mixed, durationMs: int}> */
    private array $toolCalls = [];

    /** @var array<int, array{name: string}> */
    private array $spans = [];

    public function recordSpan(string $name): void
    {
        $this->spans[] = ['name' => $name];
    }

    /** @return Collection<int, array{name: string}> */
    public function spans(): Collection
    {
        return collect($this->spans);
    }

    public function hasSpan(string $name): bool
    {
        return $this->spans()->contains(fn ($s) => $s['name'] === $name);
    }

    public function assertSpanCount(int $expected): void
    {
        $actual = count($this->spans);
        Assert::assertSame($expected, $actual, "Expected {$expected} span(s) but found {$actual}.");
    }

    public function assertHasSpan(string $name): void
    {
        Assert::assertTrue($this->hasSpan($name), "No span found with name [{$name}].");
    }

    public function assertNoSpans(): void
    {
        $this->assertSpanCount(0);
    }

    public function handleLlmCallStarted(LlmCallStarted $event): void
    {
        // Respect registered filters — same logic as GlintRecorder so that
        // Glint::filter() callbacks work correctly during tests.
        $registry = app(GlintFilterRegistry::class);

        if (! $registry->shouldRecord(new FilterEntry(
            provider: $event->provider,
            model: $event->model,
            traceId: $event->traceId,
            metadata: $event->metadata,
        ))) {
            return;
        }

        $this->generations[$event->generationId] = [
            'id' => $event->generationId,
            'provider' => $event->provider,
            'model' => $event->model,
            'name' => $event->name,
            'status' => RecordStatus::Pending,
            'completion' => null,
            'promptTokens' => 0,
            'completionTokens' => 0,
            'costUsd' => 0.0,
            'finishReason' => null,
            'errorMessage' => null,
            'metadata' => $event->metadata,
        ];
    }

    public function handleLlmCallFinished(LlmCallFinished $event): void
    {
        if (! isset($this->generations[$event->generationId])) {
            return;
        }

        $generation = $this->generations[$event->generationId];

        $costUsd = $this->pricing()->costFor(
            $generation['provider'],
            $generation['model'],
            $event->promptTokens,
            $event->completionTokens,
        );

        $this->generations[$event->generationId] = array_merge(
            $generation,
            [
                'status' => RecordStatus::Success,
                'completion' => $event->completion,
                'promptTokens' => $event->promptTokens,
                'completionTokens' => $event->completionTokens,
                'costUsd' => $costUsd,
                'finishReason' => $event->finishReason,
            ]
        );
    }

    private function pricing(): PricingRegistry
    {
        return $this->pricing ??= app(PricingRegistry::class);
    }

    public function handleLlmCallFailed(LlmCallFailed $event): void
    {
        if (! isset($this->generations[$event->generationId])) {
            return;
        }

        $this->generations[$event->generationId] = array_merge(
            $this->generations[$event->generationId],
            [
                'status' => RecordStatus::Error,
                'errorMessage' => $event->errorMessage,
            ]
        );
    }

    public function handleLlmToolCalled(LlmToolCalled $event): void
    {
        $this->toolCalls[] = [
            'spanId' => $event->spanId,
            'traceId' => $event->traceId,
            'toolName' => $event->toolName,
            'arguments' => $event->arguments,
            'result' => $event->result,
            'durationMs' => $event->durationMs,
        ];
    }

    /** @return Collection<int, CapturedGeneration> */
    public function generations(): Collection
    {
        return collect(array_values($this->generations))
            ->map(fn ($g) => new CapturedGeneration(...$g));
    }

    /** @return Collection<int, array{spanId: string, traceId: string, toolName: string, arguments: array<string, mixed>, result: mixed, durationMs: int}> */
    public function toolCalls(): Collection
    {
        return collect($this->toolCalls);
    }

    public function hasGeneration(string $provider, string $model): bool
    {
        return $this->generations()->contains(
            fn ($g) => $g->provider === $provider && $g->model === $model
        );
    }

    public function hasGenerationForName(string $name): bool
    {
        return $this->generations()->contains(fn ($g) => $g->name === $name);
    }

    public function hasToolCall(string $toolName): bool
    {
        return $this->toolCalls()->contains(fn ($t) => $t['toolName'] === $toolName);
    }

    public function assertGenerationCount(int $expected): void
    {
        $actual = $this->generations()->count();
        Assert::assertSame(
            $expected,
            $actual,
            "Expected {$expected} generation(s) but found {$actual}."
        );
    }

    public function assertHasGeneration(string $provider, string $model): void
    {
        Assert::assertTrue(
            $this->hasGeneration($provider, $model),
            "No generation found for provider [{$provider}] and model [{$model}]."
        );
    }

    public function assertGenerationForName(string $name): void
    {
        Assert::assertTrue(
            $this->hasGenerationForName($name),
            "No generation found with name [{$name}]."
        );
    }

    public function assertHasToolCall(string $toolName): void
    {
        Assert::assertTrue(
            $this->hasToolCall($toolName),
            "No tool call found for tool [{$toolName}]."
        );
    }

    public function assertNothingRecorded(): void
    {
        $this->assertNoGenerations();
        $this->assertNoToolCalls();
    }

    public function assertNoGenerations(): void
    {
        $this->assertGenerationCount(0);
    }

    public function assertMissingGeneration(string $provider, string $model): void
    {
        Assert::assertFalse(
            $this->hasGeneration($provider, $model),
            "Unexpected generation found for provider [{$provider}] and model [{$model}]."
        );
    }

    public function assertToolCallCount(int $expected): void
    {
        $actual = $this->toolCalls()->count();
        Assert::assertSame(
            $expected,
            $actual,
            "Expected {$expected} tool call(s) but found {$actual}."
        );
    }

    public function assertNoToolCalls(): void
    {
        $this->assertToolCallCount(0);
    }

    public function assertGenerationSucceeded(string $provider, string $model): void
    {
        $found = $this->generations()->first(
            fn ($g) => $g->provider === $provider && $g->model === $model
        );

        Assert::assertNotNull(
            $found,
            "No generation found for provider [{$provider}] and model [{$model}]."
        );

        Assert::assertSame(
            RecordStatus::Success,
            $found->status,
            "Generation for provider [{$provider}] and model [{$model}] did not succeed (status: {$found->status->value})."
        );
    }

    public function assertGenerationFailed(string $provider, string $model): void
    {
        $found = $this->generations()->first(
            fn ($g) => $g->provider === $provider && $g->model === $model
        );

        Assert::assertNotNull(
            $found,
            "No generation found for provider [{$provider}] and model [{$model}]."
        );

        Assert::assertSame(
            RecordStatus::Error,
            $found->status,
            "Generation for provider [{$provider}] and model [{$model}] did not fail (status: {$found->status->value})."
        );
    }

    public function flush(): void
    {
        $this->generations = [];
        $this->toolCalls = [];
        $this->spans = [];
    }
}
