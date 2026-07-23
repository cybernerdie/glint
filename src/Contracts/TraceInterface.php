<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Contracts;

interface TraceInterface
{
    public function span(string $name, callable $callback): mixed;

    public function generation(string $name, callable $callback, string $provider = '', string $model = ''): mixed;

    public function tag(string $key, string $value): static;

    /** @param array<string, string> $tags */
    public function tags(array $tags): static;

    public function end(): void;

    public function traceId(): ?string;
}
