<?php

declare(strict_types=1);

use Cybernerdie\Glint\GlintApplicationServiceProvider;
use Illuminate\Auth\Middleware\Authorize;
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

it('concrete gate() allows users whose email is in admin_emails', function () {
    config()->set('glint.admin_emails', ['admin@example.com']);

    $provider = new class($this->app) extends GlintApplicationServiceProvider {};

    $provider->boot();

    $user = new User;
    $user->forceFill(['id' => 10, 'email' => 'admin@example.com']);

    expect(Gate::forUser($user)->allows('viewGlint'))->toBeTrue();
});

it('concrete gate() denies users whose email is not in admin_emails', function () {
    config()->set('glint.admin_emails', ['admin@example.com']);

    $provider = new class($this->app) extends GlintApplicationServiceProvider {};

    $provider->boot();

    $user = new User;
    $user->forceFill(['id' => 11, 'email' => 'other@example.com']);

    expect(Gate::forUser($user)->denies('viewGlint'))->toBeTrue();
});

it('concrete gate() denies when admin_emails is empty', function () {
    config()->set('glint.admin_emails', []);

    $provider = new class($this->app) extends GlintApplicationServiceProvider {};

    $provider->boot();

    $user = new User;
    $user->forceFill(['id' => 12, 'email' => 'anyone@example.com']);

    expect(Gate::forUser($user)->denies('viewGlint'))->toBeTrue();
});

it('authorization() pushes Authorize:viewGlint into the glint-auth middleware group', function () {
    $provider = new class($this->app) extends GlintApplicationServiceProvider
    {
        protected function gate(): void
        {
            Gate::define('viewGlint', fn ($user) => true);
        }
    };

    $provider->boot();

    $groups = $this->app['router']->getMiddlewareGroups();

    expect($groups['glint-auth'])->toContain(Authorize::class.':viewGlint');
});
