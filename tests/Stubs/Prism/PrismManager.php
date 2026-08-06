<?php

declare(strict_types=1);

namespace Prism\Prism;

use Illuminate\Contracts\Foundation\Application;
use Prism\Prism\Enums\Provider as ProviderEnum;
use Prism\Prism\Providers\Provider;

class PrismManager
{
    /** @var array<string, \Closure> */
    protected array $customCreators = [];

    public function __construct(protected Application $app) {}

    public function resolve(ProviderEnum|string $name, array $providerConfig = []): Provider
    {
        $key = $name instanceof ProviderEnum ? $name->value : strtolower($name);

        if (isset($this->customCreators[$key])) {
            $result = ($this->customCreators[$key])($this->app, $providerConfig);

            if (! $result instanceof Provider) {
                throw new \UnexpectedValueException('Custom creator must return a Provider.');
            }

            return $result;
        }

        return new Providers\Anthropic;
    }

    public function extend(string $name, \Closure $callback): static
    {
        $this->customCreators[strtolower($name)] = $callback;

        return $this;
    }
}
