<?php

declare(strict_types=1);

use Cybernerdie\Glint\Pricing\PricingRegistry;

it('calculates cost correctly for a known provider and model', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    $cost = $registry->costFor('openai', 'gpt-4o', 1_000_000, 1_000_000);

    expect($cost)->toBe(12.5);
});

it('calculates partial token costs correctly', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    $cost = $registry->costFor('openai', 'gpt-4o', 100, 50);

    $expected = round((100 / 1_000_000 * 2.50) + (50 / 1_000_000 * 10.00), 8);

    expect($cost)->toBe($expected);
});

it('returns 0.0 for an unknown provider', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    expect($registry->costFor('unknown-provider', 'some-model', 1000, 500))->toBe(0.0);
});

it('returns 0.0 for an unknown model', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    expect($registry->costFor('openai', 'gpt-99-ultra', 1000, 500))->toBe(0.0);
});

it('returns 0.0 when the pricing file does not exist', function () {
    $registry = new PricingRegistry('/non/existent/path.json');

    expect($registry->costFor('openai', 'gpt-4o', 1000, 500))->toBe(0.0);
});

it('returns all pricing data', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    $all = $registry->all();

    expect($all)
        ->toBeArray()
        ->toHaveKey('openai')
        ->toHaveKey('anthropic')
        ->toHaveKey('gemini');
});

it('returns pricing data for a single provider', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    $openai = $registry->forProvider('openai');

    expect($openai)
        ->toBeArray()
        ->toHaveKey('gpt-4o')
        ->toHaveKey('gpt-4o-mini');
});

it('returns an empty array for an unknown provider', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    expect($registry->forProvider('unknown'))->toBe([]);
});

it('correctly identifies whether a provider + model combination exists', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    expect($registry->has('openai', 'gpt-4o'))->toBeTrue();
    expect($registry->has('openai', 'gpt-99-ultra'))->toBeFalse();
    expect($registry->has('unknown', 'gpt-4o'))->toBeFalse();
});

it('does not include the _comment key in the all() output', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    expect($registry->all())->not->toHaveKey('_comment');
});

it('calculates cost for anthropic models', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');

    $cost = $registry->costFor('anthropic', 'claude-sonnet-4-5', 500, 250);

    $expected = round((500 / 1_000_000 * 3.00) + (250 / 1_000_000 * 15.00), 8);

    expect($cost)->toBe($expected);
});

it('loads pricing lazily — file is not read until first use', function () {
    $registry = new PricingRegistry('/non/existent/path.json');

    expect($registry)->toBeInstanceOf(PricingRegistry::class);

    expect($registry->costFor('openai', 'gpt-4o', 100, 50))->toBe(0.0);
});

it('returns 0.0 when pricing file contains invalid JSON', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'glint_pricing_test_');
    file_put_contents($tmpFile, 'not valid json }{');

    $registry = new PricingRegistry($tmpFile);

    expect($registry->costFor('openai', 'gpt-4o', 100, 50))->toBe(0.0);

    unlink($tmpFile);
});

it('returns 0.0 when pricing file exists but cannot be read', function () {
    $tmpFile = tempnam(sys_get_temp_dir(), 'glint_pricing_noperm_');
    file_put_contents($tmpFile, '{"openai":{"gpt-4o":{"input":2.5,"output":10.0}}}');
    chmod($tmpFile, 0000);

    $registry = new PricingRegistry($tmpFile);

    set_error_handler(fn () => true);
    $cost = $registry->costFor('openai', 'gpt-4o', 1000, 500);
    restore_error_handler();

    expect($cost)->toBe(0.0);

    chmod($tmpFile, 0644);
    unlink($tmpFile);
})->skip(fn () => posix_getuid() === 0, 'Skipped when running as root');
