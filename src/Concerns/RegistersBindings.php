<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Concerns;

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Contracts\GlintClientInterface;
use Cybernerdie\Glint\Filtering\GlintFilterRegistry;
use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;

trait RegistersBindings
{
    private function registerBindings(): void
    {
        // TraceContext is scoped — re-created per request in Octane environments.
        // This prevents cross-request trace leakage.
        $this->app->scoped(TraceContext::class, fn () => new TraceContext);

        // PricingRegistry is a singleton — loaded once, JSON parsed once.
        $pricingPath = Config::string('glint.pricing_path', config_path('glint_pricing.json'));
        $this->app->singleton(
            PricingRegistry::class,
            fn ($app) => new PricingRegistry($pricingPath)
        );

        // GlintFilterRegistry is a singleton — filters are registered at boot
        // and must outlive the scoped GlintManager instance. In Octane, the
        // manager is re-created per request but filters should persist forever.
        $this->app->singleton(GlintFilterRegistry::class, fn () => new GlintFilterRegistry);

        // GlintManager is scoped — re-created per request in Octane so that
        // it always holds the current request's TraceContext instance.
        $this->app->scoped(
            GlintManager::class,
            fn (Application $app) => new GlintManager(
                $app->make(TraceContext::class),
                $app->make(PricingRegistry::class),
            )
        );

        $this->app->alias(GlintManager::class, 'glint');
        $this->app->alias(GlintManager::class, GlintClientInterface::class);
    }
}
