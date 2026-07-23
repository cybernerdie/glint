<?php

declare(strict_types=1);

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Jobs\RecordLlmCallJob;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Recorders\GlintRecorder;

beforeEach(function () {
    $context = app(TraceContext::class);
    $context->openTrace('trace-job-test');
});

it('handles LlmCallStarted event by calling the recorder and creating a generation row', function () {
    $event = new LlmCallStarted(
        generationId: 'gen-job-001',
        provider: 'openai',
        model: 'gpt-4o',
        messages: [['role' => 'user', 'content' => 'Test']],
        temperature: 0.5,
        maxTokens: 100,
        isStreaming: false,
        traceId: 'trace-job-test',
    );

    $job = new RecordLlmCallJob($event);

    $recorder = app(GlintRecorder::class);
    $job->handle($recorder);

    expect(GlintGeneration::where('id', 'gen-job-001')->exists())->toBeTrue();
});

it('handles LlmCallFinished event by calling the recorder and updating the generation row', function () {
    $startEvent = new LlmCallStarted(
        generationId: 'gen-job-002',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: 'trace-job-test',
    );

    $startJob = new RecordLlmCallJob($startEvent);
    $recorder = app(GlintRecorder::class);
    $startJob->handle($recorder);

    $finishEvent = new LlmCallFinished(
        generationId: 'gen-job-002',
        completion: 'Hello from job',
        promptTokens: 20,
        completionTokens: 10,
        finishReason: 'stop',
        durationMs: 400,
    );

    $finishJob = new RecordLlmCallJob($finishEvent);
    $finishJob->handle($recorder);

    $row = GlintGeneration::where('id', 'gen-job-002')->first();

    expect($row->status)->toBe(RecordStatus::Success)
        ->and($row->completion)->toBe('Hello from job')
        ->and((int) $row->prompt_tokens)->toBe(20);
});

it('failed() method logs a warning and does not throw', function () {
    $event = new LlmCallStarted(
        generationId: 'gen-job-fail',
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
    );

    $job = new RecordLlmCallJob($event);

    expect(fn () => $job->failed(new RuntimeException('Job processing failed')))->not->toThrow(Throwable::class);
});

it('resolves GlintRecorder from the container in the handle method', function () {
    $event = LlmCallFailed::fromThrowable(
        generationId: 'gen-job-003-nonexistent',
        exception: new RuntimeException('Oops'),
        durationMs: 30,
    );

    $job = new RecordLlmCallJob($event);

    $recorder = app(GlintRecorder::class);

    expect(fn () => $job->handle($recorder))->not->toThrow(Throwable::class);
});

it('releases finish events when the generation start row is not available yet', function () {
    $event = new LlmCallFinished(
        generationId: 'gen-job-finish-before-start',
        completion: null,
        promptTokens: 10,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 100,
    );

    $job = (new RecordLlmCallJob($event))->withFakeQueueInteractions();

    $job->handle(app(GlintRecorder::class));

    $job->assertReleased(delay: 1);
});

it('does not release finish events after the start row exists', function () {
    GlintGeneration::create([
        'id' => 'gen-job-finish-after-start',
        'trace_id' => 'trace-job-test',
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'status' => RecordStatus::Pending,
        'started_at' => now(),
    ]);

    $event = new LlmCallFinished(
        generationId: 'gen-job-finish-after-start',
        completion: null,
        promptTokens: 10,
        completionTokens: 5,
        finishReason: 'stop',
        durationMs: 100,
    );

    $job = (new RecordLlmCallJob($event))->withFakeQueueInteractions();

    $job->handle(app(GlintRecorder::class));

    $job->assertNotReleased();
});

it('handles LlmToolCalled event by calling the recorder', function () {
    $event = new LlmToolCalled(
        spanId: 'span-job-001',
        traceId: 'trace-job-test',
        parentSpanId: null,
        toolName: 'search',
        arguments: ['query' => 'hello'],
        result: ['hits' => 5],
        durationMs: 100,
    );

    $job = new RecordLlmCallJob($event);
    $recorder = app(GlintRecorder::class);

    expect(fn () => $job->handle($recorder))->not->toThrow(Throwable::class);
});
