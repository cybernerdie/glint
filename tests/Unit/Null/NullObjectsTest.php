<?php

declare(strict_types=1);

use Cybernerdie\Glint\Null\NullGeneration;
use Cybernerdie\Glint\Null\NullSpan;
use Cybernerdie\Glint\Null\NullTrace;

it('NullTrace::traceId returns null', function () {
    expect((new NullTrace)->traceId())->toBeNull();
});

it('NullTrace::tag returns self', function () {
    $trace = new NullTrace;
    expect($trace->tag('key', 'val'))->toBe($trace);
});

it('NullTrace::tags returns self', function () {
    $trace = new NullTrace;
    expect($trace->tags(['key' => 'val']))->toBe($trace);
});

it('NullTrace::end executes without error', function () {
    expect(fn () => (new NullTrace)->end())->not->toThrow(Throwable::class);
});

it('NullTrace::span calls callback with NullSpan and returns result', function () {
    $trace = new NullTrace;
    $called = false;

    $result = $trace->span('test-span', function ($span) use (&$called) {
        $called = true;
        expect($span)->toBeInstanceOf(NullSpan::class);

        return 'span-result';
    });

    expect($called)->toBeTrue()
        ->and($result)->toBe('span-result');
});

it('NullTrace::generation calls callback with NullGeneration and returns result', function () {
    $trace = new NullTrace;
    $called = false;

    $result = $trace->generation('test-gen', function ($gen) use (&$called) {
        $called = true;
        expect($gen)->toBeInstanceOf(NullGeneration::class);

        return 'gen-result';
    });

    expect($called)->toBeTrue()
        ->and($result)->toBe('gen-result');
});

it('NullSpan::spanId returns null', function () {
    expect((new NullSpan)->spanId())->toBeNull();
});

it('NullSpan::tag returns self', function () {
    $span = new NullSpan;
    expect($span->tag('key', 'val'))->toBe($span);
});

it('NullSpan::tags returns self', function () {
    $span = new NullSpan;
    expect($span->tags(['key' => 'val']))->toBe($span);
});

it('NullSpan::end executes without error', function () {
    expect(fn () => (new NullSpan)->end())->not->toThrow(Throwable::class);
});

it('NullGeneration::generationId returns null', function () {
    expect((new NullGeneration)->generationId())->toBeNull();
});

it('NullGeneration::tag returns self', function () {
    $gen = new NullGeneration;
    expect($gen->tag('key', 'val'))->toBe($gen);
});

it('NullGeneration::tags returns self', function () {
    $gen = new NullGeneration;
    expect($gen->tags(['key' => 'val']))->toBe($gen);
});

it('NullGeneration::finish executes without error', function () {
    expect(fn () => (new NullGeneration)->finish('ok', 10, 5))->not->toThrow(Throwable::class);
});

it('NullGeneration::fail executes without error', function () {
    expect(fn () => (new NullGeneration)->fail(new RuntimeException('err')))->not->toThrow(Throwable::class);
});
