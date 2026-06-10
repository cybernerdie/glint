<?php

declare(strict_types=1);

use Cybernerdie\Glint\GlintApplicationServiceProvider;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;

it('boots with the gate defined by a concrete subclass', function () {
    $provider = new class($this->app) extends GlintApplicationServiceProvider
    {
        protected function gate(): void
        {
            Gate::define('viewGlint', fn ($user) => true);
        }
    };

    $provider->boot();

    expect(Gate::has('viewGlint'))->toBeTrue();
});

it('register() is a no-op and does not throw', function () {
    $provider = new class($this->app) extends GlintApplicationServiceProvider
    {
        protected function gate(): void {}
    };

    expect(fn () => $provider->register())->not->toThrow(Throwable::class);
});

it('authorization() adds glint-auth middleware group', function () {
    $provider = new class($this->app) extends GlintApplicationServiceProvider
    {
        protected function gate(): void
        {
            Gate::define('viewGlint', fn ($user) => true);
        }
    };

    $provider->boot();

    $middlewareGroups = $this->app['router']->getMiddlewareGroups();

    expect(array_key_exists('glint-auth', $middlewareGroups))->toBeTrue();
});

it('boot() runs without error in local environment', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $provider = new class($this->app) extends GlintApplicationServiceProvider
    {
        protected function gate(): void
        {
            Gate::define('viewGlint', fn ($user) => false);
        }
    };

    expect(fn () => $provider->boot())->not->toThrow(Throwable::class);
});

it('boot() runs without error in production environment', function () {
    $provider = new class($this->app) extends GlintApplicationServiceProvider
    {
        protected function gate(): void
        {
            Gate::define('viewGlint', fn ($user) => true);
        }
    };

    $provider->boot();

    expect(Gate::has('viewGlint'))->toBeTrue();
});

it('Gate::before returns true for viewGlint in local environment', function () {
    $this->app->detectEnvironment(fn () => 'local');

    $provider = new class($this->app) extends GlintApplicationServiceProvider
    {
        protected function gate(): void
        {
            Gate::define('viewGlint', fn ($user) => false);
        }
    };

    $provider->boot();

    $user = new User;
    $user->forceFill(['id' => 1, 'email' => 'dev@example.com']);

    expect(Gate::forUser($user)->allows('viewGlint'))->toBeTrue();
});

it('Gate::before returns null for non-viewGlint abilities', function () {
    $provider = new class($this->app) extends GlintApplicationServiceProvider
    {
        protected function gate(): void
        {
            Gate::define('viewGlint', fn ($user) => false);
            Gate::define('someOtherAbility', fn ($user) => false);
        }
    };

    $provider->boot();

    $user = new User;
    $user->forceFill(['id' => 1, 'email' => 'user@example.com']);

    expect(Gate::forUser($user)->allows('someOtherAbility'))->toBeFalse();
});

it('denies authenticated users when the gate returns false in a non-local environment', function () {
    // The test environment is "testing" — the local bypass in Gate::before does NOT apply.
    $provider = new class($this->app) extends GlintApplicationServiceProvider
    {
        protected function gate(): void
        {
            Gate::define('viewGlint', fn ($user) => false);
        }
    };

    $provider->boot();

    $user = new User;
    $user->forceFill(['id' => 1, 'email' => 'restricted@example.com']);

    expect(Gate::forUser($user)->denies('viewGlint'))->toBeTrue();
});
