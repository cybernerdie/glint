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
        // Scoped so Octane re-creates it per request, preventing cross-request state leakage.
        $this->app->scoped(TraceContext::class, fn () => new TraceContext);

        // Path resolved lazily so test environments can override glint.pricing_path
        // in getEnvironmentSetUp() (which runs after register() in Testbench).
        $this->app->singleton(
            PricingRegistry::class,
            fn ($app) => new PricingRegistry(
                Config::string('glint.pricing_path', config_path('glint_pricing.json'))
            )
        );

        $this->app->singleton(GlintFilterRegistry::class, fn () => new GlintFilterRegistry);

        // Scoped so it always holds the current request's TraceContext in Octane.
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
