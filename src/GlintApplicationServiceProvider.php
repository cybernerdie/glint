<?php

declare(strict_types=1);

namespace Cybernerdie\Glint;

use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

abstract class GlintApplicationServiceProvider extends ServiceProvider
{
    final public function boot(): void
    {
        $this->gate();
        $this->authorization();
    }

    /**
     * Define the Glint dashboard authorization gate.
     *
     * Implement this method to define who can access the Glint dashboard.
     * The gate closure receives the authenticated user (never null — guests
     * are rejected before the gate is evaluated).
     *
     * Example:
     *   Gate::define('viewGlint', fn ($user) => in_array($user->email, [
     *       'admin@example.com',
     *   ]));
     */
    abstract protected function gate(): void;

    /**
     * Register the Glint dashboard authorization middleware.
     *
     * This method wires the `viewGlint` gate into the `glint` middleware
     * group so that every dashboard route is protected. The check is skipped
     * in the local environment to ease development — exactly like Telescope.
     *
     * SECURITY: The local environment bypass grants unrestricted dashboard
     * access to all users (including unauthenticated guests) whenever
     * APP_ENV=local. Never set APP_ENV=local on staging or production servers.
     */
    protected function authorization(): void
    {
        $this->app->make(Router::class)->pushMiddlewareToGroup('glint-auth', Authorize::class.':viewGlint');

        Gate::before(function ($user, $ability) {
            if ($ability === 'viewGlint' && $this->app->environment('local')) {
                return true;
            }

            return null;
        });
    }

    final public function register(): void
    {
        //
    }
}
