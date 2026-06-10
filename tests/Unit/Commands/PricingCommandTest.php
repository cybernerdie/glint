<?php

declare(strict_types=1);

use Cybernerdie\Glint\Console\Commands\PricingCommand;
use Cybernerdie\Glint\Pricing\PricingRegistry;

it('has the correct signature', function () {
    $registry = new PricingRegistry(__DIR__.'/../../../pricing/providers.json');
    expect((new PricingCommand($registry))->getName())->toBe('glint:pricing');
});

it('displays a table of providers and models', function () {
    $this->artisan('glint:pricing')
        ->expectsOutputToContain('Provider')
        ->assertSuccessful();
});

it('filters by provider when --provider option given', function () {
    $this->artisan('glint:pricing', ['--provider' => 'openai'])
        ->expectsOutputToContain('Openai')
        ->assertSuccessful();
});

it('shows warning when filtered provider has no data', function () {
    $this->artisan('glint:pricing', ['--provider' => 'nonexistent-provider'])
        ->expectsOutputToContain('No pricing data found')
        ->assertSuccessful();
});

it('displays pricing rows for known models', function () {
    $this->artisan('glint:pricing', ['--provider' => 'openai'])
        ->expectsOutputToContain('gpt-4o')
        ->assertSuccessful();
});
