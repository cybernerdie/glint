<?php

declare(strict_types=1);

use Cybernerdie\Glint\Contracts\GlintClientInterface;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Filtering\FilterEntry;
use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\Testing\FakeGeneration;
use Cybernerdie\Glint\Testing\FakeSpan;
use Cybernerdie\Glint\Testing\FakeTrace;
use Cybernerdie\Glint\Testing\GlintFake;
use Cybernerdie\Glint\Testing\RecordingStore;
use PHPUnit\Framework\AssertionFailedError;

it('isEnabled always returns true', function (): void {
    $fake = new GlintFake(new RecordingStore);

    expect($fake->isEnabled())->toBeTrue();
});

it('trace returns FakeTrace', function (): void {
    $fake = new GlintFake(new RecordingStore);

    expect($fake->trace('test'))->toBeInstanceOf(FakeTrace::class);
});

it('span returns FakeSpan', function (): void {
    $fake = new GlintFake(new RecordingStore);

    expect($fake->span('test'))->toBeInstanceOf(FakeSpan::class);
});

it('FakeSpan tags returns self for chaining', function (): void {
    $span = new FakeSpan;

    $result = $span->tags(['env' => 'test', 'model' => 'gpt-4o']);

    expect($result)->toBe($span);
});

it('FakeTrace tags returns self for chaining', function (): void {
    $trace = new FakeTrace;

    $result = $trace->tags(['env' => 'test', 'user' => '99']);

    expect($result)->toBe($trace);
});

it('FakeGeneration tags returns self for chaining', function (): void {
    $gen = new FakeGeneration('gen-001');

    $result = $gen->tags(['env' => 'test', 'model' => 'gpt-4o']);

    expect($result)->toBe($gen);
});

it('generation returns FakeGeneration', function (): void {
    $fake = GlintFake::swap();

    expect($fake->generation('test', 'openai', 'gpt-4o'))->toBeInstanceOf(FakeGeneration::class);
});

it('fake() swaps container binding', function (): void {
    $fake = GlintFake::swap();

    expect(app('glint'))->toBe($fake)
        ->and(app(GlintClientInterface::class))->toBe($fake);
});

it('captures generations fired via events after swap', function (): void {
    $fake = GlintFake::swap();

    event(new LlmCallStarted(
        generationId: 'gen-fake-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-001',
        parentSpanId: null,
        metadata: [],
        name: 'fake-test',
    ));

    event(new LlmCallFinished(
        generationId: 'gen-fake-001',
        completion: 'Great!',
        promptTokens: 8,
        completionTokens: 4,
        finishReason: 'stop',
        durationMs: 150,
    ));

    expect($fake->generations())->toHaveCount(1);

    $gen = $fake->generations()->first();
    expect($gen->id)->toBe('gen-fake-001')
        ->and($gen->provider)->toBe('openai')
        ->and($gen->model)->toBe('gpt-4o')
        ->and($gen->status)->toBe(RecordStatus::Success)
        ->and($gen->completion)->toBe('Great!')
        ->and($gen->promptTokens)->toBe(8)
        ->and($gen->completionTokens)->toBe(4);
});

it('assertHasGeneration passes when generation exists', function (): void {
    $fake = GlintFake::swap();

    event(new LlmCallStarted(
        generationId: 'gen-assert-001',
        provider: 'anthropic',
        model: 'claude-3-5-sonnet-20241022',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-001',
        name: 'assert-test',
    ));

    expect(fn () => $fake->assertHasGeneration('anthropic', 'claude-3-5-sonnet-20241022'))
        ->not->toThrow(Throwable::class);
});

it('flush empties the store', function (): void {
    $fake = GlintFake::swap();

    event(new LlmCallStarted(
        generationId: 'gen-flush-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-001',
        name: 'flush-test',
    ));

    expect($fake->generations())->toHaveCount(1);

    $fake->flush();

    expect($fake->generations())->toHaveCount(0);
});

it('store() returns the underlying RecordingStore', function (): void {
    $store = new RecordingStore;
    $fake = new GlintFake($store);

    expect($fake->store())->toBe($store);
});

it('toolCalls() returns collection from store', function (): void {
    $fake = GlintFake::swap();

    event(new LlmToolCalled(
        spanId: 'span-001',
        traceId: 'trace-001',
        parentSpanId: null,
        toolName: 'search',
        arguments: ['query' => 'test'],
        result: ['hits' => 3],
        durationMs: 50,
    ));

    expect($fake->toolCalls())->toHaveCount(1);
    expect($fake->toolCalls()->first()['toolName'])->toBe('search');
});

it('assertHasToolCall passes when tool call exists', function (): void {
    $fake = GlintFake::swap();

    event(new LlmToolCalled(
        spanId: 'span-001',
        traceId: 'trace-001',
        parentSpanId: null,
        toolName: 'calculate',
        arguments: [],
        result: null,
        durationMs: 10,
    ));

    expect(fn () => $fake->assertHasToolCall('calculate'))->not->toThrow(Throwable::class);
});

it('generation() records provider/model and computes costUsd on finish', function (): void {
    $fake = GlintFake::swap();

    $gen = $fake->generation('test', 'openai', 'gpt-4o');
    $gen->finish('Hello', 10, 5);

    expect($fake->generations())->toHaveCount(1);

    $recorded = $fake->generations()->first();
    expect($recorded->provider)->toBe('openai')
        ->and($recorded->model)->toBe('gpt-4o')
        ->and($recorded->promptTokens)->toBe(10)
        ->and($recorded->completionTokens)->toBe(5)
        ->and($recorded->status)->toBe(RecordStatus::Success)
        ->and($recorded->costUsd)->toBeFloat();
});

it('FakeTrace::generation() records a generation via callback', function (): void {
    $fake = GlintFake::swap();

    $fake->trace('my-trace')->generation('my-gen', function ($gen) {
        $gen->finish('Done', 4, 2);
    });

    expect($fake->generations())->toHaveCount(1)
        ->and($fake->generations()->first()->status)->toBe(RecordStatus::Success);
});

it('FakeTrace::generation() stores provider and model when supplied', function (): void {
    $fake = GlintFake::swap();

    $fake->trace('my-trace')->generation(
        name: 'with-provider',
        callback: fn ($gen) => null,
        provider: 'anthropic',
        model: 'claude-3-5-sonnet-20241022',
    );

    $recorded = $fake->generations()->first();
    expect($recorded->provider)->toBe('anthropic')
        ->and($recorded->model)->toBe('claude-3-5-sonnet-20241022');
});

it('assertNothingRecorded passes when store is empty', function (): void {
    $fake = GlintFake::swap();

    expect(fn () => $fake->assertNothingRecorded())->not->toThrow(Throwable::class);
});

it('assertNothingRecorded fails when a generation was recorded', function (): void {
    $fake = GlintFake::swap();
    $fake->generation('test', 'openai', 'gpt-4o');

    expect(fn () => $fake->assertNothingRecorded())->toThrow(AssertionFailedError::class);
});

it('filter() blocks generations from being recorded in the fake', function (): void {
    $fake = GlintFake::swap();
    $fake->filter(fn ($e) => $e->provider !== 'openai');

    $fake->generation('blocked', 'openai', 'gpt-4o');

    $fake->assertNothingRecorded();
});

it('filter() allows non-rejected generations through', function (): void {
    $fake = GlintFake::swap();
    $fake->filter(fn ($e) => $e->provider !== 'openai');

    $fake->generation('allowed', 'anthropic', 'claude-3-5-sonnet-20241022');

    $fake->assertGenerationCount(1);
});

it('filter() delegates to GlintManager::filter()', function (): void {
    $fake = GlintFake::swap();

    $fake->filter(fn (FilterEntry $e) => $e->provider !== 'openai');

    $entry = new FilterEntry(provider: 'openai', model: 'gpt-4o', traceId: null, metadata: []);
    expect(GlintManager::shouldRecord($entry))->toBeFalse();

    $allowed = new FilterEntry(provider: 'anthropic', model: 'claude-3', traceId: null, metadata: []);
    expect(GlintManager::shouldRecord($allowed))->toBeTrue();
});

it('assertNoGenerations passes when no generations recorded', function (): void {
    $fake = GlintFake::swap();

    expect(fn () => $fake->assertNoGenerations())->not->toThrow(Throwable::class);
});

it('assertNoGenerations fails when generations exist', function (): void {
    $fake = GlintFake::swap();
    $fake->generation('test', 'openai', 'gpt-4o');

    expect(fn () => $fake->assertNoGenerations())->toThrow(AssertionFailedError::class);
});

it('assertMissingGeneration passes when provider/model not recorded', function (): void {
    $fake = GlintFake::swap();

    expect(fn () => $fake->assertMissingGeneration('openai', 'gpt-4o'))->not->toThrow(Throwable::class);
});

it('assertMissingGeneration fails when generation exists', function (): void {
    $fake = GlintFake::swap();
    $fake->generation('test', 'openai', 'gpt-4o');

    expect(fn () => $fake->assertMissingGeneration('openai', 'gpt-4o'))->toThrow(AssertionFailedError::class);
});

it('assertToolCallCount passes when count matches', function (): void {
    $fake = GlintFake::swap();

    event(new LlmToolCalled(
        spanId: 'span-tc-001',
        traceId: 'trace-tc-001',
        parentSpanId: null,
        toolName: 'get_data',
        arguments: [],
        result: null,
        durationMs: 10,
    ));

    expect(fn () => $fake->assertToolCallCount(1))->not->toThrow(Throwable::class);
});

it('assertNoToolCalls passes when no tool calls recorded', function (): void {
    $fake = GlintFake::swap();

    expect(fn () => $fake->assertNoToolCalls())->not->toThrow(Throwable::class);
});

it('assertGenerationSucceeded passes for a completed generation', function (): void {
    $fake = GlintFake::swap();
    $gen = $fake->generation('test', 'openai', 'gpt-4o');
    $gen->finish('Done', 5, 3);

    expect(fn () => $fake->assertGenerationSucceeded('openai', 'gpt-4o'))->not->toThrow(Throwable::class);
});

it('assertGenerationFailed passes for a failed generation', function (): void {
    $fake = GlintFake::swap();
    $gen = $fake->generation('test', 'anthropic', 'claude-3-5-sonnet-20241022');
    $gen->fail(new RuntimeException('API error'));

    expect(fn () => $fake->assertGenerationFailed('anthropic', 'claude-3-5-sonnet-20241022'))->not->toThrow(Throwable::class);
});

it('assertGenerationSucceeded fails for a failed generation', function (): void {
    $fake = GlintFake::swap();

    event(new LlmCallStarted(
        generationId: 'gen-succ-fail-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-001',
        name: 'test',
    ));

    event(LlmCallFailed::fromThrowable(
        generationId: 'gen-succ-fail-001',
        exception: new RuntimeException('error'),
        durationMs: 50,
    ));

    expect(fn () => $fake->assertGenerationSucceeded('openai', 'gpt-4o'))->toThrow(AssertionFailedError::class);
});

it('assertGenerationForName passes when generation with that name exists', function (): void {
    $fake = GlintFake::swap();

    event(new LlmCallStarted(
        generationId: 'gen-name-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-001',
        name: 'my-summariser',
    ));

    expect(fn () => $fake->assertGenerationForName('my-summariser'))->not->toThrow(Throwable::class);
});

it('assertGenerationForName fails when no generation with that name exists', function (): void {
    $fake = GlintFake::swap();

    expect(fn () => $fake->assertGenerationForName('missing-name'))->toThrow(AssertionFailedError::class);
});

it('assertGenerationCount passes with correct count', function (): void {
    $fake = GlintFake::swap();

    event(new LlmCallStarted(
        generationId: 'gen-count-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-count-001',
        name: 'count-test',
    ));

    expect(fn () => $fake->assertGenerationCount(1))->not->toThrow(Throwable::class);
});

it('FakeSpan tag returns self', function (): void {
    $span = new FakeSpan;

    expect($span->tag('env', 'test'))->toBe($span);
});

it('FakeSpan end executes without error', function (): void {
    $span = new FakeSpan;
    $span->end();

    expect(true)->toBeTrue();
});

it('FakeSpan spanId returns a non-empty string', function (): void {
    $span = new FakeSpan;

    expect($span->spanId())->toBeString()->not->toBeEmpty();
});

it('FakeGeneration tag returns self', function (): void {
    $fake = GlintFake::swap();
    $gen = $fake->generation('test', 'openai', 'gpt-4o');

    expect($gen->tag('env', 'test'))->toBe($gen);
});

it('FakeGeneration fail records error status', function (): void {
    $fake = GlintFake::swap();
    $gen = $fake->generation('test', 'openai', 'gpt-4o');

    $gen->fail(new RuntimeException('something went wrong'));

    $recorded = $fake->generations()->first();
    expect($recorded->status)->toBe(RecordStatus::Error);
});

it('FakeGeneration generationId returns a non-empty string', function (): void {
    $fake = GlintFake::swap();
    $gen = $fake->generation('test', 'openai', 'gpt-4o');

    expect($gen->generationId())->toBeString()->not->toBeEmpty();
});

it('FakeTrace span calls callback with FakeSpan and returns result', function (): void {
    $fake = GlintFake::swap();
    $trace = $fake->trace('my-trace');

    $spanRef = null;
    $result = $trace->span('my-span', function ($span) use (&$spanRef) {
        $spanRef = $span;

        return 'span-result';
    });

    expect($spanRef)->toBeInstanceOf(FakeSpan::class);
    expect($result)->toBe('span-result');
});

it('FakeTrace tag returns self', function (): void {
    $fake = GlintFake::swap();
    $trace = $fake->trace('my-trace');

    expect($trace->tag('key', 'value'))->toBe($trace);
});

it('FakeTrace end executes without error', function (): void {
    $fake = GlintFake::swap();
    $trace = $fake->trace('my-trace');
    $trace->end();

    expect(true)->toBeTrue();
});

it('FakeTrace traceId returns a non-empty string', function (): void {
    $fake = GlintFake::swap();
    $trace = $fake->trace('my-trace');

    expect($trace->traceId())->toBeString()->not->toBeEmpty();
});
