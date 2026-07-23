<?php

declare(strict_types=1);

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\Testing\RecordedGeneration;
use Cybernerdie\Glint\Testing\RecordingStore;
use PHPUnit\Framework\AssertionFailedError;

function makeStartedEvent(string $generationId = 'gen-001', string $provider = 'openai', string $model = 'gpt-4o', string $name = 'my-gen'): LlmCallStarted
{
    return new LlmCallStarted(
        generationId: $generationId,
        provider: $provider,
        model: $model,
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-001',
        parentSpanId: null,
        metadata: [],
        name: $name,
    );
}

function makeFinishedEvent(string $generationId = 'gen-001'): LlmCallFinished
{
    return new LlmCallFinished(
        generationId: $generationId,
        completion: 'Hello!',
        promptTokens: 10,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 200,
    );
}

function makeFailedEvent(string $generationId = 'gen-001', string $message = 'Rate limit'): LlmCallFailed
{
    return LlmCallFailed::fromThrowable(
        generationId: $generationId,
        exception: new RuntimeException($message),
        durationMs: 50,
    );
}

function makeToolCalledEvent(string $toolName = 'get_weather'): LlmToolCalled
{
    return new LlmToolCalled(
        spanId: 'span-001',
        traceId: 'trace-001',
        parentSpanId: null,
        toolName: $toolName,
        arguments: ['location' => 'London'],
        result: ['temp' => 18],
        durationMs: 100,
    );
}

it('starts empty', function (): void {
    $store = new RecordingStore;

    expect($store->generations())->toHaveCount(0)
        ->and($store->toolCalls())->toHaveCount(0);
});

it('records a generation on LlmCallStarted', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent());

    $generations = $store->generations();

    expect($generations)->toHaveCount(1);

    $gen = $generations->first();
    expect($gen)->toBeInstanceOf(RecordedGeneration::class)
        ->and($gen->id)->toBe('gen-001')
        ->and($gen->provider)->toBe('openai')
        ->and($gen->model)->toBe('gpt-4o')
        ->and($gen->name)->toBe('my-gen')
        ->and($gen->status)->toBe(RecordStatus::Pending)
        ->and($gen->completion)->toBeNull()
        ->and($gen->promptTokens)->toBe(0)
        ->and($gen->completionTokens)->toBe(0)
        ->and($gen->costUsd)->toBe(0.0)
        ->and($gen->finishReason)->toBeNull()
        ->and($gen->errorMessage)->toBeNull();
});

it('updates generation on LlmCallFinished', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent());
    $store->handleLlmCallFinished(makeFinishedEvent());

    $gen = $store->generations()->first();

    expect($gen->status)->toBe(RecordStatus::Success)
        ->and($gen->completion)->toBe('Hello!')
        ->and($gen->promptTokens)->toBe(10)
        ->and($gen->completionTokens)->toBe(5)
        ->and($gen->finishReason)->toBe('stop');
});

it('updates generation status on LlmCallFailed', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent());
    $store->handleLlmCallFailed(makeFailedEvent(message: 'Rate limit'));

    $gen = $store->generations()->first();

    expect($gen->status)->toBe(RecordStatus::Error)
        ->and($gen->errorMessage)->toBe('Rate limit');
});

it('records a tool call on LlmToolCalled', function (): void {
    $store = new RecordingStore;
    $store->handleLlmToolCalled(makeToolCalledEvent('get_weather'));

    $toolCalls = $store->toolCalls();

    expect($toolCalls)->toHaveCount(1)
        ->and($toolCalls->first()['toolName'])->toBe('get_weather')
        ->and($toolCalls->first()['spanId'])->toBe('span-001')
        ->and($toolCalls->first()['traceId'])->toBe('trace-001');
});

it('hasGeneration returns true for matching provider and model', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent(provider: 'openai', model: 'gpt-4o'));

    expect($store->hasGeneration('openai', 'gpt-4o'))->toBeTrue()
        ->and($store->hasGeneration('anthropic', 'claude-3'))->toBeFalse();
});

it('hasToolCall returns true for matching tool name', function (): void {
    $store = new RecordingStore;
    $store->handleLlmToolCalled(makeToolCalledEvent('get_weather'));

    expect($store->hasToolCall('get_weather'))->toBeTrue()
        ->and($store->hasToolCall('send_email'))->toBeFalse();
});

it('assertGenerationCount passes when count matches', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent('gen-001'));
    $store->handleLlmCallStarted(makeStartedEvent('gen-002'));

    expect(fn () => $store->assertGenerationCount(2))->not->toThrow(Throwable::class);
});

it('assertGenerationCount fails when count does not match', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent('gen-001'));

    expect(fn () => $store->assertGenerationCount(3))->toThrow(AssertionFailedError::class);
});

it('flush clears all recordings', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent());
    $store->handleLlmToolCalled(makeToolCalledEvent());

    $store->flush();

    expect($store->generations())->toHaveCount(0)
        ->and($store->toolCalls())->toHaveCount(0);
});

it('handleLlmCallFinished does nothing when generation id not found', function (): void {
    $store = new RecordingStore;

    $store->handleLlmCallFinished(makeFinishedEvent('non-existent-id'));

    expect($store->generations())->toHaveCount(0);
});

it('handleLlmCallFailed does nothing when generation id not found', function (): void {
    $store = new RecordingStore;

    $store->handleLlmCallFailed(makeFailedEvent('non-existent-id'));

    expect($store->generations())->toHaveCount(0);
});

it('hasGenerationForName returns true for matching name', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent(name: 'my-special-gen'));

    expect($store->hasGenerationForName('my-special-gen'))->toBeTrue()
        ->and($store->hasGenerationForName('other-gen'))->toBeFalse();
});

it('assertHasToolCall passes when tool call exists', function (): void {
    $store = new RecordingStore;
    $store->handleLlmToolCalled(makeToolCalledEvent('search_web'));

    expect(fn () => $store->assertHasToolCall('search_web'))->not->toThrow(Throwable::class);
});

it('assertHasToolCall fails when tool call does not exist', function (): void {
    $store = new RecordingStore;

    expect(fn () => $store->assertHasToolCall('missing_tool'))->toThrow(AssertionFailedError::class);
});

it('assertNothingRecorded passes on empty store', function (): void {
    $store = new RecordingStore;

    expect(fn () => $store->assertNothingRecorded())->not->toThrow(Throwable::class);
});

it('assertNothingRecorded fails when generations exist', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent());

    expect(fn () => $store->assertNothingRecorded())->toThrow(AssertionFailedError::class);
});

it('assertNothingRecorded fails when tool calls exist', function (): void {
    $store = new RecordingStore;
    $store->handleLlmToolCalled(makeToolCalledEvent());

    expect(fn () => $store->assertNothingRecorded())->toThrow(AssertionFailedError::class);
});

it('handleLlmCallStarted is skipped when a filter rejects the entry', function (): void {
    GlintManager::filter(fn ($e) => $e->provider !== 'openai');

    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent(provider: 'openai', model: 'gpt-4o'));

    expect($store->generations())->toHaveCount(0);
});

it('handleLlmCallStarted records when filter allows the entry', function (): void {
    GlintManager::filter(fn ($e) => $e->provider !== 'openai');

    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent(provider: 'anthropic', model: 'claude-3'));

    expect($store->generations())->toHaveCount(1);
});

it('assertNoGenerations passes on empty store', function (): void {
    $store = new RecordingStore;

    expect(fn () => $store->assertNoGenerations())->not->toThrow(Throwable::class);
});

it('assertNoGenerations fails when generations exist', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent());

    expect(fn () => $store->assertNoGenerations())->toThrow(AssertionFailedError::class);
});

it('assertMissingGeneration passes when generation does not exist', function (): void {
    $store = new RecordingStore;

    expect(fn () => $store->assertMissingGeneration('openai', 'gpt-4o'))->not->toThrow(Throwable::class);
});

it('assertMissingGeneration fails when generation exists', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent(provider: 'openai', model: 'gpt-4o'));

    expect(fn () => $store->assertMissingGeneration('openai', 'gpt-4o'))->toThrow(AssertionFailedError::class);
});

it('assertToolCallCount passes when count matches', function (): void {
    $store = new RecordingStore;
    $store->handleLlmToolCalled(makeToolCalledEvent());

    expect(fn () => $store->assertToolCallCount(1))->not->toThrow(Throwable::class);
});

it('assertToolCallCount fails when count does not match', function (): void {
    $store = new RecordingStore;

    expect(fn () => $store->assertToolCallCount(2))->toThrow(AssertionFailedError::class);
});

it('assertNoToolCalls passes on empty store', function (): void {
    $store = new RecordingStore;

    expect(fn () => $store->assertNoToolCalls())->not->toThrow(Throwable::class);
});

it('assertNoToolCalls fails when tool calls exist', function (): void {
    $store = new RecordingStore;
    $store->handleLlmToolCalled(makeToolCalledEvent());

    expect(fn () => $store->assertNoToolCalls())->toThrow(AssertionFailedError::class);
});

it('assertGenerationSucceeded passes when generation succeeded', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent(provider: 'openai', model: 'gpt-4o'));
    $store->handleLlmCallFinished(makeFinishedEvent());

    expect(fn () => $store->assertGenerationSucceeded('openai', 'gpt-4o'))->not->toThrow(Throwable::class);
});

it('assertGenerationSucceeded fails when generation is still pending', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent(provider: 'openai', model: 'gpt-4o'));

    expect(fn () => $store->assertGenerationSucceeded('openai', 'gpt-4o'))->toThrow(AssertionFailedError::class);
});

it('assertGenerationSucceeded fails when generation does not exist', function (): void {
    $store = new RecordingStore;

    expect(fn () => $store->assertGenerationSucceeded('openai', 'gpt-4o'))->toThrow(AssertionFailedError::class);
});

it('assertGenerationFailed passes when generation failed', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent(provider: 'anthropic', model: 'claude-3-5-sonnet-20241022'));
    $store->handleLlmCallFailed(makeFailedEvent(message: 'Rate limit'));

    expect(fn () => $store->assertGenerationFailed('anthropic', 'claude-3-5-sonnet-20241022'))->not->toThrow(Throwable::class);
});

it('assertGenerationFailed fails when generation succeeded', function (): void {
    $store = new RecordingStore;
    $store->handleLlmCallStarted(makeStartedEvent(provider: 'openai', model: 'gpt-4o'));
    $store->handleLlmCallFinished(makeFinishedEvent());

    expect(fn () => $store->assertGenerationFailed('openai', 'gpt-4o'))->toThrow(AssertionFailedError::class);
});

it('assertGenerationFailed fails when generation does not exist', function (): void {
    $store = new RecordingStore;

    expect(fn () => $store->assertGenerationFailed('openai', 'gpt-4o'))->toThrow(AssertionFailedError::class);
});
