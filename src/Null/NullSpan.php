<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Null;

use Cybernerdie\Glint\Contracts\SpanInterface;

final class NullSpan implements SpanInterface
{
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

    public function spanId(): ?string
    {
        return null;
    }
}
