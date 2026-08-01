<?php

declare(strict_types=1);

use Cybernerdie\Glint\Aggregates\GenerationAggregateRecorder;
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
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Recorders\GlintRecorder;
use Cybernerdie\Glint\Support\Redactor;
use Illuminate\Support\Facades\DB;

function makeRecorder(?TraceContext $context = null, ?PricingRegistry $pricing = null): GlintRecorder
{
    $context ??= tap(new TraceContext, fn ($ctx) => $ctx->openTrace('000000000000trace-test-001'));
    $pricing ??= new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    return new GlintRecorder(
        $context,
        $pricing,
        app(GlintFilterRegistry::class),
        app(Redactor::class),
        app(GenerationAggregateRecorder::class),
    );
}

it('creates a glint_generations row with status=pending on LlmCallStarted', function () {
    $recorder = makeRecorder();

    $event = new LlmCallStarted(
        generationId: '0000000000000000000gen-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: [['role' => 'user', 'content' => 'Hello']],
        temperature: 0.7,
        maxTokens: 100,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    );

    $recorder->handleLlmCallStarted($event);

    $row = GlintGeneration::where('id', '0000000000000000000gen-001')->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe(RecordStatus::Pending)
        ->and($row->provider)->toBe('openai')
        ->and($row->model)->toBe('gpt-4o')
        ->and($row->trace_id)->toBe('000000000000trace-test-001')
        ->and($row->is_streaming)->toBeFalse();
});

it('redacts generation prompt and metadata on LlmCallStarted', function () {
    config()->set('glint.privacy.redact_patterns', ['/secret-token-\w+/']);

    $recorder = makeRecorder();

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '00000000gen-redacted-start',
        provider: 'openai',
        model: 'gpt-4o',
        messages: [['role' => 'user', 'content' => 'use secret-token-abc123']],
        temperature: 0.7,
        maxTokens: 100,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
        metadata: ['api_key' => 'secret-token-abc123'],
    ));

    $row = GlintGeneration::where('id', '00000000gen-redacted-start')->first();

    expect($row->prompt[0]['content'])->toBe('use [REDACTED]')
        ->and($row->metadata['api_key'])->toBe('[REDACTED]');
});

it('auto-creates a headless trace when traceId is null and no trace is open', function () {
    $context = new TraceContext;

    $recorder = makeRecorder($context);

    $event = new LlmCallStarted(
        generationId: '00000000000000gen-no-trace',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: null,
    );

    $recorder->handleLlmCallStarted($event);

    $gen = GlintGeneration::find('00000000000000gen-no-trace');
    expect($gen)->not->toBeNull();

    $trace = GlintTrace::find($gen->trace_id);
    expect($trace)->not->toBeNull()
        ->and($trace->name)->toBe('auto:openai/gpt-4o')
        ->and($trace->status)->toBe(RecordStatus::Pending);
});

it('closes the headless trace with status=success when LlmCallFinished fires', function () {
    $context = new TraceContext;
    $recorder = makeRecorder($context);

    $started = new LlmCallStarted(
        generationId: '0000000gen-headless-finish',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: null,
    );
    $recorder->handleLlmCallStarted($started);

    $gen = GlintGeneration::find('0000000gen-headless-finish');
    $traceId = $gen->trace_id;

    $recorder->handleLlmCallFinished(new LlmCallFinished(
        generationId: '0000000gen-headless-finish',
        completion: 'done',
        promptTokens: 5,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 100,
    ));

    $trace = GlintTrace::find($traceId);
    expect($trace->status)->toBe(RecordStatus::Success)
        ->and($trace->ended_at)->not->toBeNull();
});

it('closes a persisted auto trace when LlmCallFinished is handled by a different context', function () {
    $startContext = new TraceContext;
    $startRecorder = makeRecorder($startContext);

    $startRecorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: 'gen-headless-finish-other-',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: null,
    ));

    $gen = GlintGeneration::find('gen-headless-finish-other-');
    $traceId = $gen->trace_id;

    $finishRecorder = makeRecorder(new TraceContext);
    $finishRecorder->handleLlmCallFinished(new LlmCallFinished(
        generationId: 'gen-headless-finish-other-',
        completion: 'done',
        promptTokens: 5,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 100,
    ));

    $trace = GlintTrace::find($traceId);
    expect($trace->status)->toBe(RecordStatus::Success)
        ->and($trace->ended_at)->not->toBeNull();
});

it('closes the headless trace with status=error when LlmCallFailed fires', function () {
    $context = new TraceContext;
    $recorder = makeRecorder($context);

    $started = new LlmCallStarted(
        generationId: '000000000gen-headless-fail',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: null,
    );
    $recorder->handleLlmCallStarted($started);

    $gen = GlintGeneration::find('000000000gen-headless-fail');
    $traceId = $gen->trace_id;

    $recorder->handleLlmCallFailed(LlmCallFailed::fromThrowable(
        generationId: '000000000gen-headless-fail',
        exception: new RuntimeException('timeout'),
        durationMs: 50,
    ));

    $trace = GlintTrace::find($traceId);
    expect($trace->status)->toBe(RecordStatus::Error)
        ->and($trace->ended_at)->not->toBeNull();
});

it('closes a persisted auto trace when LlmCallFailed is handled by a different context', function () {
    $startContext = new TraceContext;
    $startRecorder = makeRecorder($startContext);

    $startRecorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: 'gen-headless-fail-other-cx',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: null,
    ));

    $gen = GlintGeneration::find('gen-headless-fail-other-cx');
    $traceId = $gen->trace_id;

    $finishRecorder = makeRecorder(new TraceContext);
    $finishRecorder->handleLlmCallFailed(LlmCallFailed::fromThrowable(
        generationId: 'gen-headless-fail-other-cx',
        exception: new RuntimeException('timeout'),
        durationMs: 50,
    ));

    $trace = GlintTrace::find($traceId);
    expect($trace->status)->toBe(RecordStatus::Error)
        ->and($trace->ended_at)->not->toBeNull();
});

it('updates generation with tokens, cost, finish_reason, and status=success on LlmCallFinished', function () {
    $recorder = makeRecorder();

    $startedEvent = new LlmCallStarted(
        generationId: '000000000000gen-finish-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: [['role' => 'user', 'content' => 'Hello']],
        temperature: 0.5,
        maxTokens: 200,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    );
    $recorder->handleLlmCallStarted($startedEvent);

    $finishedEvent = new LlmCallFinished(
        generationId: '000000000000gen-finish-001',
        completion: 'Hello there!',
        promptTokens: 10,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 350,
    );
    $recorder->handleLlmCallFinished($finishedEvent);

    $row = GlintGeneration::where('id', '000000000000gen-finish-001')->first();

    expect($row->status)->toBe(RecordStatus::Success)
        ->and($row->completion)->toBe('Hello there!')
        ->and((int) $row->prompt_tokens)->toBe(10)
        ->and((int) $row->completion_tokens)->toBe(5)
        ->and((int) $row->total_tokens)->toBe(15)
        ->and($row->finish_reason)->toBe('stop')
        ->and((int) $row->duration_ms)->toBe(350)
        ->and($row->ended_at)->not->toBeNull();
});

it('redacts completion on LlmCallFinished', function () {
    config()->set('glint.privacy.redact_patterns', ['/secret-token-\w+/']);

    $recorder = makeRecorder();

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '0000000gen-redacted-finish',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    ));

    $recorder->handleLlmCallFinished(new LlmCallFinished(
        generationId: '0000000gen-redacted-finish',
        completion: 'answer secret-token-abc123',
        promptTokens: 10,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 100,
    ));

    expect(GlintGeneration::find('0000000gen-redacted-finish')->completion)->toBe('answer [REDACTED]');
});

it('calculates cost via PricingRegistry on LlmCallFinished', function () {
    $recorder = makeRecorder();

    $startedEvent = new LlmCallStarted(
        generationId: '00000000000000gen-cost-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    );
    $recorder->handleLlmCallStarted($startedEvent);

    $finishedEvent = new LlmCallFinished(
        generationId: '00000000000000gen-cost-001',
        completion: 'done',
        promptTokens: 1_000_000,
        completionTokens: 1_000_000,
        finishReason: 'stop',
        durationMs: 100,
    );
    $recorder->handleLlmCallFinished($finishedEvent);

    $row = GlintGeneration::where('id', '00000000000000gen-cost-001')->first();

    expect((float) $row->cost_usd)->toBe(12.5);
});

it('updates generation status=error with error_message on LlmCallFailed', function () {
    $recorder = makeRecorder();

    $startedEvent = new LlmCallStarted(
        generationId: '00000000000000gen-fail-001',
        provider: 'anthropic',
        model: 'claude-sonnet-4-5',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    );
    $recorder->handleLlmCallStarted($startedEvent);

    $exception = new RuntimeException('Rate limit exceeded');

    $failedEvent = LlmCallFailed::fromThrowable(
        generationId: '00000000000000gen-fail-001',
        exception: $exception,
        durationMs: 50,
    );
    $recorder->handleLlmCallFailed($failedEvent);

    $row = GlintGeneration::where('id', '00000000000000gen-fail-001')->first();

    expect($row->status)->toBe(RecordStatus::Error)
        ->and($row->error_message)->toBe('Rate limit exceeded')
        ->and((int) $row->duration_ms)->toBe(50)
        ->and($row->ended_at)->not->toBeNull();
});

it('redacts error messages on LlmCallFailed', function () {
    config()->set('glint.privacy.redact_patterns', ['/secret-token-\w+/']);

    $recorder = makeRecorder();

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '000000000gen-redacted-fail',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    ));

    $recorder->handleLlmCallFailed(new LlmCallFailed(
        generationId: '000000000gen-redacted-fail',
        errorMessage: 'provider leaked secret-token-abc123',
        errorClass: RuntimeException::class,
        durationMs: 100,
    ));

    expect(GlintGeneration::find('000000000gen-redacted-fail')->error_message)->toBe('provider leaked [REDACTED]');
});

it('creates a glint_spans row with type=tool_call on LlmToolCalled', function () {
    $recorder = makeRecorder();

    $event = new LlmToolCalled(
        spanId: '0000000000000span-tool-001',
        traceId: '000000000000trace-test-001',
        parentSpanId: null,
        toolName: 'get_weather',
        arguments: ['location' => 'London'],
        result: ['temperature' => 18, 'unit' => 'celsius'],
        durationMs: 200,
    );

    $recorder->handleLlmToolCalled($event);

    $row = GlintSpan::where('id', '0000000000000span-tool-001')->first();

    expect($row)->not->toBeNull()
        ->and($row->type)->toBe(SpanType::ToolCall)
        ->and($row->name)->toBe('get_weather')
        ->and($row->trace_id)->toBe('000000000000trace-test-001')
        ->and($row->status)->toBe(RecordStatus::Success)
        ->and((int) $row->duration_ms)->toBe(200);
});

it('redacts tool input output and metadata on LlmToolCalled', function () {
    config()->set('glint.privacy.redact_patterns', ['/secret-token-\w+/']);

    $recorder = makeRecorder();

    $recorder->handleLlmToolCalled(new LlmToolCalled(
        spanId: '00000000span-redacted-tool',
        traceId: '000000000000trace-test-001',
        parentSpanId: null,
        toolName: 'lookup',
        arguments: ['token' => 'secret-token-abc123'],
        result: ['body' => 'result secret-token-def456'],
        durationMs: 25,
        metadata: ['header' => 'secret-token-ghi789'],
    ));

    $row = GlintSpan::where('id', '00000000span-redacted-tool')->first();

    expect($row->input)->toContain('[REDACTED]')
        ->and($row->input)->not->toContain('secret-token-abc123')
        ->and($row->output)->toContain('[REDACTED]')
        ->and($row->output)->not->toContain('secret-token-def456')
        ->and($row->metadata['header'])->toBe('[REDACTED]');
});

it('is silent when a duplicate generation_id causes a DB error on handleLlmCallStarted', function () {
    $context = tap(new TraceContext, fn ($ctx) => $ctx->openTrace('00000000000000trace-silent'));
    $pricing = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');
    $recorder = new GlintRecorder(
        $context,
        $pricing,
        app(GlintFilterRegistry::class),
        app(Redactor::class),
        app(GenerationAggregateRecorder::class),
    );

    $event = new LlmCallStarted(
        generationId: '000000000000gen-silent-dup',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '00000000000000trace-silent',
    );

    $recorder->handleLlmCallStarted($event);

    expect(fn () => $recorder->handleLlmCallStarted($event))->not->toThrow(Throwable::class);
});

it('is silent when handleLlmCallFinished is called with unknown generation_id', function () {
    $recorder = makeRecorder();

    $event = new LlmCallFinished(
        generationId: '000000gen-nonexistent-9999',
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
        generationId: '0gen-nonexistent-fail-9999',
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
        generationId: '0000000000gen-filtered-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    );

    $recorder->handleLlmCallStarted($event);

    expect(GlintGeneration::where('id', '0000000000gen-filtered-001')->exists())->toBeFalse();
});

it('writes directly (no rescue) when throw_on_exceptions is true', function () {
    config()->set('glint.throw_on_exceptions', true);

    $recorder = makeRecorder();

    $event = new LlmCallStarted(
        generationId: '00000000000gen-throw-happy',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    );

    $recorder->handleLlmCallStarted($event);

    expect(GlintGeneration::where('id', '00000000000gen-throw-happy')->exists())->toBeTrue();
});

it('writes aggregate rows for all four periods on LlmCallFinished', function () {
    $recorder = makeRecorder();

    $started = new LlmCallStarted(
        generationId: '00000000000gen-agg-periods',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    );
    $recorder->handleLlmCallStarted($started);

    $finished = new LlmCallFinished(
        generationId: '00000000000gen-agg-periods',
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

    $rows = DB::table('glint_aggregates')
        ->where('provider', 'openai')
        ->where('model', 'gpt-4o')
        ->get();

    foreach ($rows as $row) {
        expect($row->user_id)->toBe(GlintAggregate::GlobalDimension)
            ->and($row->team_id)->toBe(GlintAggregate::GlobalDimension);
    }
});

it('accumulates a rolling average duration across two generations in the same bucket', function () {
    $recorder = makeRecorder();

    foreach ([['id' => '00000000000000000gen-avg-1', 'dur' => 100, 'pt' => 10, 'ct' => 5], ['id' => '00000000000000000gen-avg-2', 'dur' => 300, 'pt' => 20, 'ct' => 10]] as $call) {
        $recorder->handleLlmCallStarted(new LlmCallStarted(
            generationId: $call['id'],
            provider: 'openai',
            model: 'gpt-4o',
            messages: null,
            temperature: null,
            maxTokens: null,
            isStreaming: false,
            traceId: '000000000000trace-test-001',
        ));

        $recorder->handleLlmCallFinished(new LlmCallFinished(
            generationId: $call['id'],
            completion: null,
            promptTokens: $call['pt'],
            completionTokens: $call['ct'],
            finishReason: 'stop',
            durationMs: $call['dur'],
        ));
    }

    $hour = DB::table('glint_aggregates')
        ->where('provider', 'openai')
        ->where('model', 'gpt-4o')
        ->where('period', 'hour')
        ->first();

    expect((int) $hour->total_requests)->toBe(2)
        ->and((int) $hour->successful_requests)->toBe(2)
        ->and((int) $hour->total_tokens)->toBe(45)
        ->and((int) $hour->avg_duration_ms)->toBe(200);

    expect(DB::table('glint_aggregates')
        ->where('provider', 'openai')
        ->where('model', 'gpt-4o')
        ->where('period', 'hour')
        ->where('user_id', GlintAggregate::GlobalDimension)
        ->where('team_id', GlintAggregate::GlobalDimension)
        ->count())->toBe(1);
});

it('updates aggregate failure counters when LlmCallFailed fires', function () {
    $recorder = makeRecorder();

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '00000000000000gen-agg-fail',
        provider: 'anthropic',
        model: 'claude-sonnet-4-5',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    ));

    $recorder->handleLlmCallFailed(LlmCallFailed::fromThrowable(
        generationId: '00000000000000gen-agg-fail',
        exception: new RuntimeException('rate limited'),
        durationMs: 250,
    ));

    $hour = DB::table('glint_aggregates')
        ->where('provider', 'anthropic')
        ->where('model', 'claude-sonnet-4-5')
        ->where('period', 'hour')
        ->first();

    expect($hour)->not->toBeNull()
        ->and((int) $hour->total_requests)->toBe(1)
        ->and((int) $hour->successful_requests)->toBe(0)
        ->and((int) $hour->failed_requests)->toBe(1)
        ->and((int) $hour->total_tokens)->toBe(0)
        ->and((int) $hour->avg_duration_ms)->toBe(250);
});

it('keeps aggregate counters consistent across successful and failed generations', function () {
    $recorder = makeRecorder();

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '000000000gen-mixed-success',
        provider: 'openai',
        model: 'gpt-4o-mini',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    ));

    $recorder->handleLlmCallFinished(new LlmCallFinished(
        generationId: '000000000gen-mixed-success',
        completion: null,
        promptTokens: 10,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 100,
    ));

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '000000000000gen-mixed-fail',
        provider: 'openai',
        model: 'gpt-4o-mini',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    ));

    $recorder->handleLlmCallFailed(LlmCallFailed::fromThrowable(
        generationId: '000000000000gen-mixed-fail',
        exception: new RuntimeException('timeout'),
        durationMs: 300,
    ));

    $hour = DB::table('glint_aggregates')
        ->where('provider', 'openai')
        ->where('model', 'gpt-4o-mini')
        ->where('period', 'hour')
        ->first();

    expect($hour)->not->toBeNull()
        ->and((int) $hour->total_requests)->toBe(2)
        ->and((int) $hour->successful_requests)->toBe(1)
        ->and((int) $hour->failed_requests)->toBe(1)
        ->and((int) $hour->total_tokens)->toBe(15)
        ->and((int) $hour->prompt_tokens)->toBe(10)
        ->and((int) $hour->completion_tokens)->toBe(5)
        ->and((int) $hour->avg_duration_ms)->toBe(200);
});

it('aliases duplicate generation starts with the same dedupe key', function () {
    $recorder = makeRecorder();

    $metadata = ['glint_dedupe_key' => hash('sha256', 'same-request')];

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '000000gen-dedupe-canonical',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
        metadata: $metadata,
    ));

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '000000gen-dedupe-duplicate',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
        metadata: $metadata,
    ));

    expect(GlintGeneration::where('dedupe_key', $metadata['glint_dedupe_key'])->count())->toBe(1)
        ->and(GlintGeneration::where('id', '000000gen-dedupe-duplicate')->exists())->toBeFalse();
});

it('maps duplicate terminal events to the canonical generation without double-counting', function () {
    $recorder = makeRecorder();

    $metadata = ['glint_dedupe_key' => hash('sha256', 'same-terminal-request')];

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '0000gen-terminal-canonical',
        provider: 'openai',
        model: 'gpt-4o-dedupe',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
        metadata: $metadata,
    ));

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '0000gen-terminal-duplicate',
        provider: 'openai',
        model: 'gpt-4o-dedupe',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
        metadata: $metadata,
    ));

    $recorder->handleLlmCallFinished(new LlmCallFinished(
        generationId: '0000gen-terminal-duplicate',
        completion: null,
        promptTokens: 10,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 100,
    ));

    $recorder->handleLlmCallFinished(new LlmCallFinished(
        generationId: '0000gen-terminal-canonical',
        completion: null,
        promptTokens: 10,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 100,
    ));

    $generation = GlintGeneration::find('0000gen-terminal-canonical');
    $hour = DB::table('glint_aggregates')
        ->where('provider', 'openai')
        ->where('model', 'gpt-4o-dedupe')
        ->where('period', 'hour')
        ->first();

    expect($generation->status)->toBe(RecordStatus::Success)
        ->and((int) $hour->total_requests)->toBe(1)
        ->and((int) $hour->successful_requests)->toBe(1)
        ->and((int) $hour->total_tokens)->toBe(15);
});

it('propagates DB exceptions when throw_on_exceptions is true', function () {
    config()->set('glint.throw_on_exceptions', true);

    $recorder = makeRecorder();

    DB::statement('DROP TABLE IF EXISTS glint_generations');

    $event = new LlmCallStarted(
        generationId: '0000000000000gen-throw-err',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    );

    expect(fn () => $recorder->handleLlmCallStarted($event))->toThrow(Exception::class);
});

it('is silent when the auto trace row is deleted before closeAutoTrace', function () {
    $context = new TraceContext;
    $recorder = makeRecorder($context);

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '000000000gen-deleted-trace',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: null,
    ));

    $gen = GlintGeneration::find('000000000gen-deleted-trace');
    $traceId = $gen->trace_id;

    GlintTrace::where('id', $traceId)->delete();

    expect(fn () => $recorder->handleLlmCallFinished(new LlmCallFinished(
        generationId: '000000000gen-deleted-trace',
        completion: 'done',
        promptTokens: 5,
        completionTokens: 3,
        finishReason: 'stop',
        durationMs: 100,
    )))->not->toThrow(Throwable::class);
});

it('truncate returns value unchanged when max_completion_chars is 0', function () {
    config()->set('glint.recording.max_completion_chars', 0);

    $recorder = makeRecorder();

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '000000000000gen-trunc-zero',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    ));

    $longCompletion = str_repeat('x', 100);

    $recorder->handleLlmCallFinished(new LlmCallFinished(
        generationId: '000000000000gen-trunc-zero',
        completion: $longCompletion,
        promptTokens: 5,
        completionTokens: 20,
        finishReason: 'stop',
        durationMs: 100,
    ));

    $row = GlintGeneration::find('000000000000gen-trunc-zero');
    expect($row->completion)->toBe($longCompletion);
});

it('truncate truncates completion when it exceeds max_completion_chars', function () {
    config()->set('glint.recording.max_completion_chars', 10);

    $recorder = makeRecorder();

    $recorder->handleLlmCallStarted(new LlmCallStarted(
        generationId: '00000000000gen-trunc-limit',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: '000000000000trace-test-001',
    ));

    $recorder->handleLlmCallFinished(new LlmCallFinished(
        generationId: '00000000000gen-trunc-limit',
        completion: 'This is a long completion string',
        promptTokens: 5,
        completionTokens: 10,
        finishReason: 'stop',
        durationMs: 100,
    ));

    $row = GlintGeneration::find('00000000000gen-trunc-limit');
    expect(mb_strlen((string) $row->completion))->toBe(10);
});

it('LlmToolCalled normalizes an object result to an associative array', function () {
    $obj = new stdClass;
    $obj->hits = 42;
    $obj->label = 'test';

    $event = new LlmToolCalled(
        spanId: '00000000000000span-obj-001',
        traceId: '00000000000000000trace-001',
        parentSpanId: null,
        toolName: 'search',
        arguments: [],
        result: $obj,
        durationMs: 10,
    );

    expect($event->result)->toBe(['hits' => 42, 'label' => 'test']);
});
