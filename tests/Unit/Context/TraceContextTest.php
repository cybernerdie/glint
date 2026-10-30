<?php

declare(strict_types=1);

use Cybernerdie\Glint\Context\TraceContext;

it('starts with no active trace', function () {
    $context = new TraceContext;

    expect($context->traceId())->toBeNull()
        ->and($context->activeSpanId())->toBeNull();
});

it('openTrace sets traceId', function () {
    $context = new TraceContext;
    $context->openTrace('trace-123');

    expect($context->traceId())->toBe('trace-123');
});

it('materializes a request trace only on first use', function () {
    $context = new TraceContext;
    $writes = 0;

    $context->openRequestTrace('trace-123', function () use (&$writes): bool {
        $writes++;

        return true;
    });

    expect($context->isTraceMaterialized('trace-123'))->toBeFalse()
        ->and($context->materializeTrace())->toBeTrue()
        ->and($context->materializeTrace())->toBeTrue()
        ->and($context->isTraceMaterialized('trace-123'))->toBeTrue()
        ->and($writes)->toBe(1);
});

it('does not materialize a request trace when the write fails', function () {
    $context = new TraceContext;
    $context->openRequestTrace('trace-123', fn (): bool => false);

    expect($context->materializeTrace())->toBeFalse()
        ->and($context->isTraceMaterialized('trace-123'))->toBeFalse();
});

it('closeTrace resets all state', function () {
    $context = new TraceContext;
    $context->openTrace('trace-123');
    $context->registerGeneration('gen-1', 'trace-123');
    $context->setActiveSpan('span-1');
    $context->closeTrace();

    expect($context->traceId())->toBeNull()
        ->and($context->activeSpanId())->toBeNull()
        ->and($context->traceIdForGeneration('gen-1'))->toBeNull();
});

it('registerGeneration and traceIdForGeneration round-trip', function () {
    $context = new TraceContext;
    $context->openTrace('trace-123');
    $context->registerGeneration('gen-abc', 'trace-123');

    expect($context->traceIdForGeneration('gen-abc'))->toBe('trace-123');
});

it('traceIdForGeneration falls back to active traceId for unknown generation', function () {
    $context = new TraceContext;
    $context->openTrace('trace-fallback');

    expect($context->traceIdForGeneration('unknown-gen'))->toBe('trace-fallback');
});

it('traceIdForGeneration returns null when no trace is open', function () {
    $context = new TraceContext;

    expect($context->traceIdForGeneration('unknown-gen'))->toBeNull();
});

it('setActiveSpan and activeSpanId round-trip', function () {
    $context = new TraceContext;
    $context->setActiveSpan('span-xyz');

    expect($context->activeSpanId())->toBe('span-xyz');
});

it('setActiveSpan can clear the active span with null', function () {
    $context = new TraceContext;
    $context->setActiveSpan('span-xyz');
    $context->setActiveSpan(null);

    expect($context->activeSpanId())->toBeNull();
});

it('restores the parent span when nested spans close', function () {
    $context = new TraceContext;
    $context->openTrace('trace-123');
    $context->openSpan('parent');
    $context->openSpan('child');

    expect($context->activeSpanId())->toBe('child');

    $context->closeSpan('child');

    expect($context->activeSpanId())->toBe('parent');
});

it('keeps spans isolated between nested traces', function () {
    $context = new TraceContext;
    $context->openTrace('outer');
    $context->openSpan('outer-span');
    $context->openTrace('inner');

    expect($context->activeSpanId())->toBeNull();

    $context->openSpan('inner-span');
    $context->closeTrace();

    expect($context->activeSpanId())->toBe('outer-span');
});
