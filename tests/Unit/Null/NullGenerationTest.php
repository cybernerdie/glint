<?php

declare(strict_types=1);

use Cybernerdie\Glint\Null\NullGeneration;

it('generationId returns null', function (): void {
    $gen = new NullGeneration;

    expect($gen->generationId())->toBeNull();
});

it('tag returns self for chaining', function (): void {
    $gen = new NullGeneration;

    $result = $gen->tag('env', 'production');

    expect($result)->toBe($gen);
});

it('tags returns self for chaining', function (): void {
    $gen = new NullGeneration;

    $result = $gen->tags(['env' => 'production', 'model' => 'gpt-4o']);

    expect($result)->toBe($gen);
});

it('finish does nothing', function (): void {
    $gen = new NullGeneration;

    $gen->finish('Hello world', 10, 5, 'stop');

    expect(true)->toBeTrue();
});

it('fail does nothing', function (): void {
    $gen = new NullGeneration;

    $gen->fail(new RuntimeException('Something went wrong'));

    expect(true)->toBeTrue();
});
