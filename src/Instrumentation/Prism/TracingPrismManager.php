<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation\Prism;

use Cybernerdie\Glint\Context\TraceContext;
use Illuminate\Contracts\Foundation\Application;
use Prism\Prism\Enums\Provider as ProviderEnum;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\Provider;

final class TracingPrismManager extends PrismManager
{
    public function __construct(
        private readonly PrismManager $inner,
        Application $app,
    ) {
        parent::__construct($app);
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     */
    public function resolve(ProviderEnum|string $name, array $providerConfig = []): Provider
    {
        $provider = $this->inner->resolve($name, $providerConfig);

        return new TracingProvider($provider, $this->app->make(TraceContext::class));
    }

    public function extend(string $provider, \Closure $callback): static
    {
        $this->inner->extend($provider, $callback);

        return $this;
    }

    /** @param array<int, mixed> $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->inner->$name(...$arguments);
    }
}
