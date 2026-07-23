<?php

declare(strict_types=1);

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Enums\SpanType;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Filtering\FilterEntry;
use Cybernerdie\Glint\Filtering\GlintFilterRegistry;
use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Recorders\GlintRecorder;
use Illuminate\Support\Facades\DB;

function makeRecorder(?TraceContext $context = null, ?PricingRegistry $pricing = null): GlintRecorder
{
    $context ??= tap(new TraceContext, fn ($ctx) => $ctx->openTrace('trace-test-001', true));
    $pricing ??= new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    return new GlintRecorder($context, $pricing, app(GlintFilterRegistry::class));
}

it('creates a glint_generations row with status=pending on LlmCallStarted', function () {
    $recorder = makeRecorder();

    $event = new LlmCallStarted(
        generationId: 'gen-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: [['role' => 'user', 'content' => 'Hello']],
        temperature: 0.7,
        maxTokens: 100,
        isStreaming: false,
        traceId: 'trace-test-001',
    );

    $recorder->handleLlmCallStarted($event);

    $row = GlintGeneration::where('id', 'gen-001')->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe(RecordStatus::Pending)
        ->and($row->provider)->toBe('openai')
        ->and($row->model)->toBe('gpt-4o')
        ->and($row->trace_id)->toBe('trace-test-001')
        ->and($row->is_streaming)->toBeFalse();
});

it('does nothing when isSampled() is false', function () {
    $context = new TraceContext;
    $context->openTrace('trace-unsampled', false);

    $recorder = makeRecorder($context);

    $event = new LlmCallStarted(
        generationId: 'gen-unsampled',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-unsampled',
    );

    $recorder->handleLlmCallStarted($event);

    expect(GlintGeneration::where('id', 'gen-unsampled')->exists())->toBeFalse();
});

it('auto-creates a headless trace when traceId is null and no trace is open', function () {
    $context = new TraceContext; // no openTrace call — traceId is null

    $recorder = makeRecorder($context);

    $event = new LlmCallStarted(
        generationId: 'gen-no-trace',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: null,
    );

    $recorder->handleLlmCallStarted($event);

    $gen = GlintGeneration::find('gen-no-trace');
    expect($gen)->not->toBeNull();

    $trace = GlintTrace::find($gen->trace_id);
    expect($trace)->not->toBeNull();
    expect($trace->name)->toBe('auto:openai/gpt-4o');
    expect($trace->status)->toBe(RecordStatus::Pending);
});

it('closes the headless trace with status=success when LlmCallFinished fires', function () {
    $context = new TraceContext;
    $recorder = makeRecorder($context);

    $started = new LlmCallStarted(
        generationId: 'gen-headless-finish',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: null,
    );
    $recorder->handleLlmCallStarted($started);

    $gen = GlintGeneration::find('gen-headless-finish');
    $traceId = $gen->trace_id;

    $recorder->handleLlmCallFinished(new LlmCallFinished(
        generationId: 'gen-headless-finish',
        completion: 'done',
        promptTokens: 5,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 100,
    ));

    $trace = GlintTrace::find($traceId);
    expect($trace->status)->toBe(RecordStatus::Success);
    expect($trace->ended_at)->not->toBeNull();
});

it('closes the headless trace with status=error when LlmCallFailed fires', function () {
    $context = new TraceContext;
    $recorder = makeRecorder($context);

    $started = new LlmCallStarted(
        generationId: 'gen-headless-fail',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: null,
    );
    $recorder->handleLlmCallStarted($started);

    $gen = GlintGeneration::find('gen-headless-fail');
    $traceId = $gen->trace_id;

    $recorder->handleLlmCallFailed(LlmCallFailed::fromThrowable(
        generationId: 'gen-headless-fail',
        exception: new RuntimeException('timeout'),
        durationMs: 50,
    ));

    $trace = GlintTrace::find($traceId);
    expect($trace->status)->toBe(RecordStatus::Error);
    expect($trace->ended_at)->not->toBeNull();
});

it('updates generation with tokens, cost, finish_reason, and status=success on LlmCallFinished', function () {
    $recorder = makeRecorder();

    $startedEvent = new LlmCallStarted(
        generationId: 'gen-finish-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: [['role' => 'user', 'content' => 'Hello']],
        temperature: 0.5,
        maxTokens: 200,
        isStreaming: false,
        traceId: 'trace-test-001',
    );
    $recorder->handleLlmCallStarted($startedEvent);

    $finishedEvent = new LlmCallFinished(
        generationId: 'gen-finish-001',
        completion: 'Hello there!',
        promptTokens: 10,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 350,
    );
    $recorder->handleLlmCallFinished($finishedEvent);

    $row = GlintGeneration::where('id', 'gen-finish-001')->first();

    expect($row->status)->toBe(RecordStatus::Success)
        ->and($row->completion)->toBe('Hello there!')
        ->and((int) $row->prompt_tokens)->toBe(10)
        ->and((int) $row->completion_tokens)->toBe(5)
        ->and((int) $row->total_tokens)->toBe(15)
        ->and($row->finish_reason)->toBe('stop')
        ->and((int) $row->duration_ms)->toBe(350)
        ->and($row->ended_at)->not->toBeNull();
});

it('calculates cost via PricingRegistry on LlmCallFinished', function () {
    $recorder = makeRecorder();

    $startedEvent = new LlmCallStarted(
        generationId: 'gen-cost-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-test-001',
    );
    $recorder->handleLlmCallStarted($startedEvent);

    $finishedEvent = new LlmCallFinished(
        generationId: 'gen-cost-001',
        completion: 'done',
        promptTokens: 1_000_000,
        completionTokens: 1_000_000,
        finishReason: 'stop',
        durationMs: 100,
    );
    $recorder->handleLlmCallFinished($finishedEvent);

    $row = GlintGeneration::where('id', 'gen-cost-001')->first();

    expect((float) $row->cost_usd)->toBe(12.5);
});

it('updates generation status=error with error_message on LlmCallFailed', function () {
    $recorder = makeRecorder();

    $startedEvent = new LlmCallStarted(
        generationId: 'gen-fail-001',
        provider: 'anthropic',
        model: 'claude-sonnet-4-5',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-test-001',
    );
    $recorder->handleLlmCallStarted($startedEvent);

    $exception = new RuntimeException('Rate limit exceeded');

    $failedEvent = LlmCallFailed::fromThrowable(
        generationId: 'gen-fail-001',
        exception: $exception,
        durationMs: 50,
    );
    $recorder->handleLlmCallFailed($failedEvent);

    $row = GlintGeneration::where('id', 'gen-fail-001')->first();

    expect($row->status)->toBe(RecordStatus::Error)
        ->and($row->error_message)->toBe('Rate limit exceeded')
        ->and((int) $row->duration_ms)->toBe(50)
        ->and($row->ended_at)->not->toBeNull();
});

it('creates a glint_spans row with type=tool_call on LlmToolCalled', function () {
    $recorder = makeRecorder();

    $event = new LlmToolCalled(
        spanId: 'span-tool-001',
        traceId: 'trace-test-001',
        parentSpanId: null,
        toolName: 'get_weather',
        arguments: ['location' => 'London'],
        result: ['temperature' => 18, 'unit' => 'celsius'],
        durationMs: 200,
    );

    $recorder->handleLlmToolCalled($event);

    $row = GlintSpan::where('id', 'span-tool-001')->first();

    expect($row)->not->toBeNull()
        ->and($row->type)->toBe(SpanType::ToolCall)
        ->and($row->name)->toBe('get_weather')
        ->and($row->trace_id)->toBe('trace-test-001')
        ->and($row->status)->toBe(RecordStatus::Success)
        ->and((int) $row->duration_ms)->toBe(200);
});

it('is silent when a duplicate generation_id causes a DB error on handleLlmCallStarted', function () {
    $context = tap(new TraceContext, fn ($ctx) => $ctx->openTrace('trace-silent', true));
    $pricing = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');
    $recorder = new GlintRecorder($context, $pricing, app(GlintFilterRegistry::class));

    $event = new LlmCallStarted(
        generationId: 'gen-silent-dup',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-silent',
    );

    $recorder->handleLlmCallStarted($event);

    expect(fn () => $recorder->handleLlmCallStarted($event))->not->toThrow(Throwable::class);
});

it('is silent when handleLlmCallFinished is called with unknown generation_id', function () {
    $recorder = makeRecorder();

    $event = new LlmCallFinished(
        generationId: 'gen-nonexistent-9999',
        completion: 'ok',
        promptTokens: 10,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 100,
    );

    expect(fn () => $recorder->handleLlmCallFinished($event))->not->toThrow(Throwable::class);
});

it('is silent when handleLlmCallFailed is called with unknown generation_id', function () {
    $recorder = makeRecorder();

    $event = LlmCallFailed::fromThrowable(
        generationId: 'gen-nonexistent-fail-9999',
        exception: new RuntimeException('Something went wrong'),
        durationMs: 50,
    );

    expect(fn () => $recorder->handleLlmCallFailed($event))->not->toThrow(Throwable::class);
});

it('skips recording when a filter rejects the entry', function () {
    GlintManager::filter(
        fn (FilterEntry $e) => $e->provider !== 'openai'
    );

    $recorder = makeRecorder();

    $event = new LlmCallStarted(
        generationId: 'gen-filtered-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-test-001',
    );

    $recorder->handleLlmCallStarted($event);

    expect(GlintGeneration::where('id', 'gen-filtered-001')->exists())->toBeFalse();
});

it('writes directly (no rescue) when throw_on_exceptions is true', function () {
    config()->set('glint.throw_on_exceptions', true);

    $recorder = makeRecorder();

    $event = new LlmCallStarted(
        generationId: 'gen-throw-happy',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-test-001',
    );

    $recorder->handleLlmCallStarted($event);

    expect(GlintGeneration::where('id', 'gen-throw-happy')->exists())->toBeTrue();
});

it('writes aggregate rows for all four periods on LlmCallFinished', function () {
    $recorder = makeRecorder();

    $started = new LlmCallStarted(
        generationId: 'gen-agg-periods',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-test-001',
    );
    $recorder->handleLlmCallStarted($started);

    $finished = new LlmCallFinished(
        generationId: 'gen-agg-periods',
        completion: null,
        promptTokens: 100,
        completionTokens: 50,
        finishReason: 'stop',
        durationMs: 200,
    );
    $recorder->handleLlmCallFinished($finished);

    $periods = DB::table('glint_aggregates')
        ->where('provider', 'openai')
        ->where('model', 'gpt-4o')
        ->pluck('period')
        ->sort()
        ->values()
        ->toArray();

    expect($periods)->toBe(['day', 'hour', 'month', 'week']);
});

it('propagates DB exceptions when throw_on_exceptions is true', function () {
    config()->set('glint.throw_on_exceptions', true);

    $recorder = makeRecorder();

    DB::statement('DROP TABLE IF EXISTS glint_generations');

    $event = new LlmCallStarted(
        generationId: 'gen-throw-err',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-test-001',
    );

    expect(fn () => $recorder->handleLlmCallStarted($event))->toThrow(Exception::class);
});
