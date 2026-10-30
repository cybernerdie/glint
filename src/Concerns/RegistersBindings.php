<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Concerns;

use Cybernerdie\Glint\Aggregates\GenerationAggregateRecorder;
use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Contracts\GlintClientInterface;
use Cybernerdie\Glint\Filtering\GlintFilterRegistry;
use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Support\Redactor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;

trait RegistersBindings
{
    private function registerBindings(): void
    {
        $this->app->scoped(TraceContext::class, fn () => new TraceContext);

        $this->app->singleton(
            PricingRegistry::class,
            fn ($app) => new PricingRegistry(
                Config::string('glint.pricing_path', config_path('glint_pricing.json'))
            )
        );

        $this->app->singleton(GlintFilterRegistry::class, fn () => new GlintFilterRegistry);

        $this->app->scoped(
            GlintManager::class,
            fn (Application $app) => new GlintManager(
                $app->make(TraceContext::class),
                $app->make(PricingRegistry::class),
                $app->make(Redactor::class),
                $app->make(GenerationAggregateRecorder::class),
                $app->make(GlintFilterRegistry::class),
            )
        );

        $this->app->alias(GlintManager::class, 'glint');
        $this->app->alias(GlintManager::class, GlintClientInterface::class);
    }
}
