<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation;

use Cybernerdie\Glint\Contracts\InstrumentationDriver;
use Cybernerdie\Glint\Instrumentation\Prism\TracingPrismManager;
use Illuminate\Contracts\Foundation\Application;
use Prism\Prism\Prism;
use Prism\Prism\PrismManager;

final class PrismInstrumentation implements InstrumentationDriver
{
    public function __construct(private readonly Application $app) {}

    public function isAvailable(): bool
    {
        return class_exists(Prism::class);
    }

    public function register(): void
    {
        $this->app->extend(
            PrismManager::class,
            fn (PrismManager $manager, Application $app) => new TracingPrismManager($manager, $app)
        );
    }
}
