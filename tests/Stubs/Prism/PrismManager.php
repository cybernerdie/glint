<?php

declare(strict_types=1);

namespace Prism\Prism;

class PrismManager
{
    public function resolve(string $name): mixed
    {
        return null;
    }

    public function extend(string $name, callable $callback): static
    {
        return $this;
    }
}
