<?php

declare(strict_types=1);

use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('glint.enabled', true);
});

function glintUser(string $email = 'user@example.com'): User
{
    $user = new User;
    $user->forceFill(['id' => 1, 'email' => $email]);

    return $user;
}

it('denies access through the glint-auth group when the gate denies', function () {
    Gate::define('viewGlint', fn ($user = null) => false);
    Route::middleware('glint-auth')->get('/glint-auth-probe', fn () => 'ok');

    $this->actingAs(glintUser())
        ->get('/glint-auth-probe')
        ->assertForbidden();
});

it('allows access through the glint-auth group when the gate grants', function () {
    Gate::define('viewGlint', fn ($user) => true);
    Route::middleware('glint-auth')->get('/glint-auth-probe-allowed', fn () => 'ok');

    $this->actingAs(glintUser())
        ->get('/glint-auth-probe-allowed')
        ->assertOk();
});

it('registers the Authorize middleware on the glint-auth group by default', function () {
    $groups = $this->app['router']->getMiddlewareGroups();

    expect($groups['glint-auth'] ?? [])->toContain(
        Authorize::class.':viewGlint'
    );
});
