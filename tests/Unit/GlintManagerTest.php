<?php

declare(strict_types=1);

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Filtering\FilterEntry;
use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Null\NullGeneration;
use Cybernerdie\Glint\Null\NullSpan;
use Cybernerdie\Glint\Null\NullTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Tracing\ActiveGeneration;
use Cybernerdie\Glint\Tracing\ActiveSpan;
use Cybernerdie\Glint\Tracing\ActiveTrace;

function makeManager(bool $enabled = true): GlintManager
{
    config()->set('glint.enabled', $enabled);

    return new GlintManager(
        app(TraceContext::class),
        app(PricingRegistry::class),
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

    expect($trace)->toBeInstanceOf(ActiveTrace::class);
    expect($trace->traceId())->toBeString()->not->toBeNull();
});

it('span returns ActiveSpan when enabled', function (): void {
    $manager = makeManager(true);

    $manager->trace('parent');

    $span = $manager->span('my-span');

    expect($span)->toBeInstanceOf(ActiveSpan::class);
    expect($span->spanId())->toBeString()->not->toBeNull();
});

it('generation returns ActiveGeneration when enabled', function (): void {
    $manager = makeManager(true);

    $manager->trace('parent');

    $gen = $manager->generation('my-gen', 'openai', 'gpt-4');

    expect($gen)->toBeInstanceOf(ActiveGeneration::class);
    expect($gen->generationId())->toBeString()->not->toBeNull();
});

it('trace creates a GlintTrace row in the database', function (): void {
    $manager = makeManager(true);

    $trace = $manager->trace('db-trace', ['key' => 'value']);

    expect(GlintTrace::where('id', $trace->traceId())->exists())->toBeTrue();
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
    GlintManager::filter(fn (FilterEntry $e) => true);

    $entry = new FilterEntry(provider: 'openai', model: 'gpt-4o', traceId: null, metadata: []);

    expect(GlintManager::shouldRecord($entry))->toBeTrue();
});

it('shouldRecord returns false when a filter rejects the entry', function (): void {
    GlintManager::filter(fn (FilterEntry $e) => $e->provider !== 'openai');

    $entry = new FilterEntry(provider: 'openai', model: 'gpt-4o', traceId: null, metadata: []);

    expect(GlintManager::shouldRecord($entry))->toBeFalse();
});

