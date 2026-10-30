<?php

declare(strict_types=1);

use Cybernerdie\Glint\Aggregates\GenerationAggregateRecorder;
use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Filtering\FilterEntry;
use Cybernerdie\Glint\Filtering\GlintFilterRegistry;
use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Null\NullGeneration;
use Cybernerdie\Glint\Null\NullSpan;
use Cybernerdie\Glint\Null\NullTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Support\Redactor;
use Cybernerdie\Glint\Tracing\ActiveGeneration;
use Cybernerdie\Glint\Tracing\ActiveSpan;
use Cybernerdie\Glint\Tracing\ActiveTrace;

function makeManager(bool $enabled = true): GlintManager
{
    config()->set('glint.enabled', $enabled);

    return new GlintManager(
        app(TraceContext::class),
        app(PricingRegistry::class),
        app(Redactor::class),
        app(GenerationAggregateRecorder::class),
        app(GlintFilterRegistry::class),
    );
}

it('isEnabled returns false when config disabled', function (): void {
    $manager = makeManager(false);

    expect($manager->isEnabled())->toBeFalse();
});

it('isEnabled returns true when config enabled', function (): void {
    $manager = makeManager(true);

    expect($manager->isEnabled())->toBeTrue();
});

it('trace returns NullTrace when disabled', function (): void {
    $manager = makeManager(false);

    $trace = $manager->trace('test');

    expect($trace)->toBeInstanceOf(NullTrace::class);
});

it('span returns NullSpan when disabled', function (): void {
    $manager = makeManager(false);

    $span = $manager->span('test');

    expect($span)->toBeInstanceOf(NullSpan::class);
});

it('generation returns NullGeneration when disabled', function (): void {
    $manager = makeManager(false);

    $gen = $manager->generation('test', 'openai', 'gpt-4');

    expect($gen)->toBeInstanceOf(NullGeneration::class);
});

it('trace returns ActiveTrace when enabled', function (): void {
    $manager = makeManager(true);

    $trace = $manager->trace('my-trace');

    expect($trace)->toBeInstanceOf(ActiveTrace::class)
        ->and($trace->traceId())->toBeString()->not->toBeNull();
});

it('span returns ActiveSpan when enabled', function (): void {
    $manager = makeManager(true);

    $manager->trace('parent');

    $span = $manager->span('my-span');

    expect($span)->toBeInstanceOf(ActiveSpan::class)
        ->and($span->spanId())->toBeString()->not->toBeNull();
});

it('generation returns ActiveGeneration when enabled', function (): void {
    $manager = makeManager(true);

    $manager->trace('parent');

    $gen = $manager->generation('my-gen', 'openai', 'gpt-4');

    expect($gen)->toBeInstanceOf(ActiveGeneration::class)
        ->and($gen->generationId())->toBeString()->not->toBeNull();
});

it('trace creates a GlintTrace row in the database', function (): void {
    $manager = makeManager(true);

    $trace = $manager->trace('db-trace', ['key' => 'value']);

    expect(GlintTrace::where('id', $trace->traceId())->exists())->toBeTrue();
});

it('redacts metadata supplied to manual traces spans and generations', function (): void {
    config()->set('glint.privacy.redact_patterns', ['/secret-token-\w+/']);
    $manager = makeManager(true);

    $trace = $manager->trace('private-trace', ['token' => 'secret-token-trace']);
    $span = $manager->span('private-span', ['token' => 'secret-token-span']);
    $generation = $manager->generation('private-generation', 'openai', 'gpt-4o', ['token' => 'secret-token-generation']);

    expect(GlintTrace::find($trace->traceId())->metadata['token'])->toBe('[REDACTED]')
        ->and(GlintSpan::find($span->spanId())->metadata['token'])->toBe('[REDACTED]')
        ->and(GlintGeneration::find($generation->generationId())->metadata['token'])->toBe('[REDACTED]');
});

it('span creates a GlintSpan row in the database', function (): void {
    $manager = makeManager(true);
    $manager->trace('parent');

    $span = $manager->span('db-span');

    expect(GlintSpan::where('id', $span->spanId())->exists())->toBeTrue();
});

it('generation creates a GlintGeneration row in the database', function (): void {
    $manager = makeManager(true);
    $manager->trace('parent');

    $gen = $manager->generation('db-gen', 'anthropic', 'claude-3-5-sonnet-20241022');

    expect(GlintGeneration::where('id', $gen->generationId())->exists())->toBeTrue();
});

it('filter registers a callback', function (): void {
    app(GlintManager::class)->filter(fn (FilterEntry $e) => true);

    $entry = new FilterEntry(provider: 'openai', model: 'gpt-4o', traceId: null, metadata: []);

    expect(app(GlintManager::class)->shouldRecord($entry))->toBeTrue();
});

it('shouldRecord returns false when a filter rejects the entry', function (): void {
    app(GlintManager::class)->filter(fn (FilterEntry $e) => $e->provider !== 'openai');

    $entry = new FilterEntry(provider: 'openai', model: 'gpt-4o', traceId: null, metadata: []);

    expect(app(GlintManager::class)->shouldRecord($entry))->toBeFalse();
});

it('trace() executes the write directly when throw_on_exceptions is true', function (): void {
    config()->set('glint.throw_on_exceptions', true);

    $manager = makeManager(true);
    $trace = $manager->trace('protected-write-trace');

    expect($trace)->toBeInstanceOf(ActiveTrace::class)
        ->and(GlintTrace::where('name', 'protected-write-trace')->exists())->toBeTrue();
});

it('span creates a headless trace when no trace context is open', function (): void {
    $manager = makeManager(true);

    $span = $manager->span('headless-span');

    expect($span)->toBeInstanceOf(ActiveSpan::class)
        ->and(GlintTrace::where('name', 'auto:headless-span')->exists())->toBeTrue();
});

it('keeps a headless manual span active until it ends', function (): void {
    $manager = makeManager(true);
    $context = app(TraceContext::class);

    $span = $manager->span('headless-parent');
    $generation = $manager->generation('child-generation', 'openai', 'gpt-4o');

    expect($context->activeSpanId())->toBe($span->spanId())
        ->and(GlintGeneration::find($generation->generationId())->parent_span_id)->toBe($span->spanId())
        ->and(GlintGeneration::find($generation->generationId())->trace_id)->toBe(GlintSpan::find($span->spanId())->trace_id);

    $span->end();

    expect($context->traceId())->toBeNull()
        ->and($context->activeSpanId())->toBeNull()
        ->and(GlintTrace::find(GlintSpan::find($span->spanId())->trace_id)->status)->toBe(RecordStatus::Success);
});

it('closes a headless manual generation trace on completion', function (): void {
    $manager = makeManager(true);
    $generation = $manager->generation('headless-generation', 'openai', 'gpt-4o');
    $traceId = GlintGeneration::find($generation->generationId())->trace_id;

    $generation->finish('Done', 10, 5);

    expect(GlintTrace::find($traceId)->status)->toBe(RecordStatus::Success)
        ->and(GlintTrace::find($traceId)->ended_at)->not->toBeNull();
});

it('closes a headless manual generation trace on failure', function (): void {
    $manager = makeManager(true);
    $generation = $manager->generation('headless-generation', 'openai', 'gpt-4o');
    $traceId = GlintGeneration::find($generation->generationId())->trace_id;

    $generation->fail(new RuntimeException('Provider failed'));

    expect(GlintTrace::find($traceId)->status)->toBe(RecordStatus::Error);
});

it('generation creates a headless trace when no trace context is open', function (): void {
    $manager = makeManager(true);

    $gen = $manager->generation('headless-gen', 'openai', 'gpt-4o');

    expect($gen)->toBeInstanceOf(ActiveGeneration::class)
        ->and(GlintTrace::where('name', 'like', 'auto:%')->exists())->toBeTrue();
});
