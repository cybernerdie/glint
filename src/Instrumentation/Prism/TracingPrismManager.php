<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation\Prism;

use Cybernerdie\Glint\Context\TraceContext;
use Illuminate\Contracts\Foundation\Application;
use Prism\Prism\PrismManager;

final class TracingPrismManager extends PrismManager
{
    public function __construct(
        private readonly PrismManager $inner,
        private readonly Application $app,
    ) {}

    public function resolve(string $name, mixed $providerConfig = null): TracingProvider
    {
        $provider = $this->inner->resolve($name);

        return new TracingProvider(is_object($provider) ? $provider : new \stdClass, $this->app->make(TraceContext::class));
    }

    public function extend(string $name, callable $callback): static
    {
        $this->inner->extend($name, $callback);

        return $this;
    }

    /** @param array<int, mixed> $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->inner->$name(...$arguments);
    }
}
