<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Contracts;

interface SpanInterface
{
    public function tag(string $key, string $value): static;

    /** @param array<string, string> $tags */
    public function tags(array $tags): static;

    public function end(): void;

    public function spanId(): ?string;
}
