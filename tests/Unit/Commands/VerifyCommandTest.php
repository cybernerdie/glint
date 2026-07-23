<?php

declare(strict_types=1);

use Cybernerdie\Glint\Console\Commands\VerifyCommand;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Illuminate\Console\Command;

it('has the correct signature', function () {
    expect((new VerifyCommand)->getName())->toBe('glint:verify');
});

it('shows DISABLED and returns FAILURE when glint.enabled is false', function () {
    config()->set('glint.enabled', false);

    $this->artisan('glint:verify')
        ->expectsOutputToContain('DISABLED')
        ->assertExitCode(Command::FAILURE);
});

it('shows ENABLED when glint.enabled is true', function () {
    config()->set('glint.enabled', true);

    $this->artisan('glint:verify')
        ->expectsOutputToContain('ENABLED');
});

it('shows ALL PRESENT when all tables exist', function () {
    config()->set('glint.enabled', true);

    $this->artisan('glint:verify')
        ->expectsOutputToContain('ALL PRESENT');
});

it('shows queue worker hint when recording mode is queue', function () {
    config()->set('glint.enabled', true);
    config()->set('glint.recording.mode', 'queue');
    config()->set('glint.queue.connection', 'sync');
    config()->set('glint.queue.queue', 'glint');

    $this->artisan('glint:verify')
        ->expectsOutputToContain('queue:work');
});

it('does not show queue worker hint when recording mode is sync', function () {
    config()->set('glint.enabled', true);
    config()->set('glint.recording.mode', 'sync');

    $this->artisan('glint:verify')
        ->doesntExpectOutputToContain('queue:work');
});

it('shows pricing model count when pricing file is loaded', function () {
    config()->set('glint.enabled', true);

    $this->artisan('glint:verify')
        ->expectsOutputToContain('models from');
});

it('shows EMPTY when pricing file does not exist', function () {
    config()->set('glint.enabled', true);
    app()->instance(PricingRegistry::class, new PricingRegistry('/non/existent/pricing.json'));

    $this->artisan('glint:verify')
        ->expectsOutputToContain('EMPTY');
});

it('shows active drivers list', function () {
    config()->set('glint.enabled', true);
    config()->set('glint.drivers', ['http']);

    $this->artisan('glint:verify')
        ->expectsOutputToContain('http');
});

it('shows app service provider status in output', function () {
    config()->set('glint.enabled', true);

    // The verify command always outputs the app service provider check —
    // the label is present regardless of registered/not-found state.
    $this->artisan('glint:verify')
        ->expectsOutputToContain('App service provider');
});
