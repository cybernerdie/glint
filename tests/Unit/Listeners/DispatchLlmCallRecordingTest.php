<?php

declare(strict_types=1);

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Filtering\FilterEntry;
use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\Jobs\RecordLlmCallJob;
use Cybernerdie\Glint\Listeners\DispatchLlmCallRecording;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Recorders\GlintRecorder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
});

it('creates the request trace before queueing and links the generation across contexts', function () {
    $traceId = (string) Str::ulid();
    $generationId = (string) Str::ulid();
    $context = app(TraceContext::class);
    $filterChecks = 0;

    app(GlintManager::class)->filter(function (FilterEntry $entry) use (&$filterChecks): bool {
        $filterChecks++;

        return true;
    });

    $context->openRequestTrace($traceId, fn (): bool => GlintTrace::create([
        'id' => $traceId,
        'name' => 'request',
        'status' => RecordStatus::Pending,
        'started_at' => now(),
    ]) !== null);

    app(DispatchLlmCallRecording::class)->handle(new LlmCallStarted(
        generationId: $generationId,
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
    ));

    expect(GlintTrace::whereKey($traceId)->exists())->toBeTrue();
    Queue::assertPushed(RecordLlmCallJob::class, 1);

    $context->closeTrace();
    Queue::pushed(RecordLlmCallJob::class)->first()->handle(app(GlintRecorder::class));

    expect(GlintGeneration::find($generationId)?->trace_id)->toBe($traceId)
        ->and($filterChecks)->toBe(1);
});

it('does not create or queue a filtered LLM call', function () {
    $traceId = (string) Str::ulid();
    $context = app(TraceContext::class);
    $context->openRequestTrace($traceId, fn (): bool => GlintTrace::create([
        'id' => $traceId,
        'name' => 'request',
        'status' => RecordStatus::Pending,
        'started_at' => now(),
    ]) !== null);

    app(GlintManager::class)->filter(fn (FilterEntry $entry): bool => false);

    app(DispatchLlmCallRecording::class)->handle(new LlmCallStarted(
        generationId: (string) Str::ulid(),
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: $traceId,
    ));

    expect(GlintTrace::whereKey($traceId)->exists())->toBeFalse();
    Queue::assertNotPushed(RecordLlmCallJob::class);
});

it('does not interrupt the request when a recording filter fails', function () {
    $traceId = (string) Str::ulid();
    $context = app(TraceContext::class);
    $context->openRequestTrace($traceId, fn (): bool => GlintTrace::create([
        'id' => $traceId,
        'name' => 'request',
        'status' => RecordStatus::Pending,
        'started_at' => now(),
    ]) !== null);

    app(GlintManager::class)->filter(fn (FilterEntry $entry): bool => throw new RuntimeException('filter error'));

    app(DispatchLlmCallRecording::class)->handle(new LlmCallStarted(
        generationId: (string) Str::ulid(),
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
        traceId: $traceId,
    ));

    expect(GlintTrace::whereKey($traceId)->exists())->toBeFalse();
    Queue::assertNotPushed(RecordLlmCallJob::class);
});

it('uses Laravel queue defaults for blank connection and queue names', function (): void {
    config()->set('glint.queue.connection', '   ');
    config()->set('glint.queue.queue', '   ');

    app(DispatchLlmCallRecording::class)->handle(new LlmCallStarted(
        generationId: (string) Str::ulid(),
        provider: 'openai',
        model: 'gpt-4o',
        messages: null,
        temperature: null,
        maxTokens: null,
        isStreaming: false,
    ));

    $job = Queue::pushed(RecordLlmCallJob::class)->first();

    expect($job->connection)->toBeNull()
        ->and($job->queue)->toBeNull();
});
