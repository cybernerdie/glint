<?php

declare(strict_types=1);

namespace Cybernerdie\Glint;

use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
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
     * Override this in App\Providers\GlintServiceProvider to grant access.
     * The default restricts the dashboard to the emails listed in
     * config('glint.admin_emails').
     */
    protected function gate(): void
    {
        Gate::define('viewGlint', function (Authenticatable $user): bool {
            $emails = config('glint.admin_emails', []);

            if (! is_array($emails) || $emails === [] || ! $user instanceof Model) {
                return false;
            }

            $email = $user->getAttribute('email');

            return is_string($email) && in_array($email, $emails, true);
        });
    }

    protected function authorization(): void
    {
        $router = $this->app->make(Router::class);
        $groups = $router->getMiddlewareGroups();
        $authMiddleware = Authorize::class.':viewGlint';
        $existing = is_array($groups['glint-auth'] ?? null) ? $groups['glint-auth'] : [];

        if (! in_array($authMiddleware, $existing, true)) {
            $router->pushMiddlewareToGroup('glint-auth', $authMiddleware);
        }

        Gate::before(function (?Authenticatable $user, string $ability): ?bool {
            if ($ability === 'viewGlint' && $this->app->environment('local')) {
                return true;
            }

            return null;
        });
    }

    final public function register(): void {}
}
