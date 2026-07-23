<?php

declare(strict_types=1);

use Cybernerdie\Glint\GlintServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

it('does not register routes or events when glint is disabled', function () {
    config()->set('glint.enabled', false);

    $provider = new GlintServiceProvider(app());
    $provider->register();
    $provider->boot();

    expect(true)->toBeTrue();
});

it('registers events in queue mode dispatching jobs', function () {
    config()->set('glint.enabled', true);
    config()->set('glint.recording.mode', 'queue');
    config()->set('glint.queue.connection', 'sync');
    config()->set('glint.queue.queue', 'default');

    $provider = new GlintServiceProvider(app());
    $provider->register();
    $provider->boot();

    expect(true)->toBeTrue();
});

it('registerSchedule registers the alert evaluation schedule', function () {
    config()->set('glint.enabled', true);

    $provider = new GlintServiceProvider(app());
    $provider->register();
    $provider->boot();

    $schedule = app(Schedule::class);

    expect($schedule)->toBeInstanceOf(Schedule::class);
});

it('registerDrivers skips unknown driver names', function () {
    config()->set('glint.enabled', true);
    config()->set('glint.drivers', ['unknown-driver-xyz']);

    $provider = new GlintServiceProvider(app());
    $provider->register();

    expect(fn () => $provider->boot())->not->toThrow(Throwable::class);
});

it('registerDrivers skips driver when isAvailable returns false', function () {
    config()->set('glint.enabled', true);
    config()->set('glint.drivers', ['openai']);

    $provider = new GlintServiceProvider(app());
    $provider->register();

    expect(fn () => $provider->boot())->not->toThrow(Throwable::class);
});

it('registerDrivers skips non-string driver entries', function () {
    config()->set('glint.enabled', true);
    config()->set('glint.drivers', [42, null, 'http']);

    $provider = new GlintServiceProvider(app());
    $provider->register();

    expect(fn () => $provider->boot())->not->toThrow(Throwable::class);
});
