<?php

declare(strict_types=1);

use Cybernerdie\Glint\Instrumentation\Prism\TracingPrismManager;
use Cybernerdie\Glint\Instrumentation\Prism\TracingProvider;
use Prism\Prism\PrismManager;

it('resolve wraps the inner provider in a TracingProvider', function () {
    $inner = new PrismManager;
    $manager = new TracingPrismManager($inner, app());

    $result = $manager->resolve('openai');

    expect($result)->toBeInstanceOf(TracingProvider::class);
});

it('extend delegates to inner PrismManager and returns self', function () {
    $inner = new PrismManager;
    $manager = new TracingPrismManager($inner, app());

    $result = $manager->extend('custom', fn () => new stdClass);

    expect($result)->toBe($manager);
});

it('__call forwards unknown method calls to inner manager', function () {
    $inner = new class extends PrismManager
    {
        public function testMethod(string $arg): string
        {
            return 'result:'.$arg;
        }
    };

    $manager = new TracingPrismManager($inner, app());

    $result = $manager->testMethod('hello');

    expect($result)->toBe('result:hello');
});
