<?php

declare(strict_types=1);

namespace Prism\Prism;

use Illuminate\Contracts\Foundation\Application;
use Prism\Prism\Enums\Provider as ProviderEnum;
use Prism\Prism\Providers\Provider;

class PrismManager
{
    public function __construct(protected Application $app) {}

    public function resolve(ProviderEnum|string $name, array $providerConfig = []): Provider
    {
        return new Providers\Anthropic;
    }

    public function extend(string $name, callable $callback): static
    {
        return $this;
    }
}
