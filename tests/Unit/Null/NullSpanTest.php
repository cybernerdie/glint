<?php

declare(strict_types=1);

use Cybernerdie\Glint\Null\NullSpan;

it('spanId returns null', function (): void {
    $span = new NullSpan;

    expect($span->spanId())->toBeNull();
});

it('tag returns self for chaining', function (): void {
    $span = new NullSpan;

    $result = $span->tag('env', 'production');

    expect($result)->toBe($span);
});

it('tags returns self for chaining', function (): void {
    $span = new NullSpan;

    $result = $span->tags(['env' => 'production', 'model' => 'gpt-4o']);

    expect($result)->toBe($span);
});

it('end does nothing', function (): void {
    $span = new NullSpan;

    $span->end();

    expect(true)->toBeTrue();
});
