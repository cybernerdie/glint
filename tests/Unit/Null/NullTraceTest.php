<?php

declare(strict_types=1);

use Cybernerdie\Glint\Null\NullTrace;

it('traceId returns null', function (): void {
    $trace = new NullTrace;

    expect($trace->traceId())->toBeNull();
});

it('span invokes callback and returns result', function (): void {
    $trace = new NullTrace;
    $called = false;

    $result = $trace->span('my-span', function ($arg) use (&$called) {
        $called = true;

        return 'span-result';
    });

    expect($called)->toBeTrue();
    expect($result)->toBe('span-result');
});

it('generation invokes callback and returns result', function (): void {
    $trace = new NullTrace;
    $called = false;

    $result = $trace->generation('my-gen', function ($arg) use (&$called) {
        $called = true;

        return 'gen-result';
    });

    expect($called)->toBeTrue();
    expect($result)->toBe('gen-result');
});

it('tag returns self for chaining', function (): void {
    $trace = new NullTrace;

    $result = $trace->tag('env', 'production');

    expect($result)->toBe($trace);
});

it('tags returns self for chaining', function (): void {
    $trace = new NullTrace;

    $result = $trace->tags(['env' => 'production', 'user' => '42']);

    expect($result)->toBe($trace);
});

it('end does nothing', function (): void {
    $trace = new NullTrace;

    $trace->end();

    expect(true)->toBeTrue();
});
