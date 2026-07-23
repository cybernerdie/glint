<?php

declare(strict_types=1);

use Cybernerdie\Glint\Console\Commands\PricingCommand;
use Cybernerdie\Glint\Models\GlintGeneration;

it('has the correct signature', function () {
    expect((new PricingCommand)->getName())->toBe('glint:pricing');
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

it('reports unknown recorded models', function () {
    GlintGeneration::factory()->create([
        'provider' => 'custom-provider',
        'model' => 'missing',
        'started_at' => now(),
    ]);

    GlintGeneration::factory()->create([
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'started_at' => now(),
    ]);

    $this->artisan('glint:pricing', ['--unknown' => true])
        ->expectsOutputToContain('custom-provider')
        ->expectsOutputToContain('missing')
        ->assertSuccessful();
});

it('reports no unknown models when recorded models exist in registry', function () {
    GlintGeneration::factory()->create([
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'started_at' => now(),
    ]);

    $this->artisan('glint:pricing', ['--unknown' => true])
        ->expectsOutputToContain('No unknown priced models found')
        ->assertSuccessful();
});
