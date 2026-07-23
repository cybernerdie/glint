<?php

declare(strict_types=1);

use Cybernerdie\Glint\Context\TraceContext;

it('starts with no active trace', function () {
    $context = new TraceContext;

    expect($context->traceId())->toBeNull();
    expect($context->isSampled())->toBeTrue();
    expect($context->activeSpanId())->toBeNull();
});

it('openTrace sets traceId and sampled flag', function () {
    $context = new TraceContext;
    $context->openTrace('trace-123', true);

    expect($context->traceId())->toBe('trace-123');
    expect($context->isSampled())->toBeTrue();
});

it('openTrace sets sampled to false when not sampled', function () {
    $context = new TraceContext;
    $context->openTrace('trace-abc', false);

    expect($context->isSampled())->toBeFalse();
});

it('closeTrace resets all state', function () {
    $context = new TraceContext;
    $context->openTrace('trace-123', true);
    $context->registerGeneration('gen-1', 'trace-123');
    $context->setActiveSpan('span-1');
    $context->closeTrace();

    expect($context->traceId())->toBeNull();
    expect($context->isSampled())->toBeTrue();
    expect($context->activeSpanId())->toBeNull();
    expect($context->traceIdForGeneration('gen-1'))->toBeNull();
});

it('registerGeneration and traceIdForGeneration round-trip', function () {
    $context = new TraceContext;
    $context->openTrace('trace-123', true);
    $context->registerGeneration('gen-abc', 'trace-123');

    expect($context->traceIdForGeneration('gen-abc'))->toBe('trace-123');
});

it('traceIdForGeneration falls back to active traceId for unknown generation', function () {
    $context = new TraceContext;
    $context->openTrace('trace-fallback', true);

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
