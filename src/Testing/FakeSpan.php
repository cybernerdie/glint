<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Testing;

use Cybernerdie\Glint\Contracts\SpanInterface;
use Illuminate\Support\Str;

final class FakeSpan implements SpanInterface
{
    private readonly string $spanId;

    public function __construct()
    {
        $this->spanId = (string) Str::ulid();
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

    public function spanId(): string
    {
        return $this->spanId;
    }
}
