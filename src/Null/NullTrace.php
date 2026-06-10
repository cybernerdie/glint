<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Null;

use Cybernerdie\Glint\Contracts\TraceInterface;

final class NullTrace implements TraceInterface
{
    public function span(string $name, callable $callback): mixed
    {
        return $callback(new NullSpan);
    }

    public function generation(string $name, callable $callback, string $provider = '', string $model = ''): mixed
    {
        return $callback(new NullGeneration);
    }

    public function tag(string $key, string $value): static
    {
        return $this;
    }

    /** @param array<string, string> $tags */
    public function tags(array $tags): static
    {
        return $this;
    }

    public function end(): void {}

    public function traceId(): ?string
    {
        return null;
    }
}
