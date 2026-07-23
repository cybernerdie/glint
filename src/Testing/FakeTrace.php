<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Testing;

use Cybernerdie\Glint\Contracts\TraceInterface;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Illuminate\Support\Str;

final class FakeTrace implements TraceInterface
{
    private readonly string $traceId;

    public function __construct()
    {
        $this->traceId = (string) Str::ulid();
    }

    public function span(string $name, callable $callback): mixed
    {
        return $callback(new FakeSpan);
    }

    public function generation(string $name, callable $callback, string $provider = '', string $model = ''): mixed
    {
        $generationId = (string) Str::ulid();

        event(new LlmCallStarted(
            generationId: $generationId,
            provider: $provider,
            model: $model,
            messages: null,
            temperature: null,
            maxTokens: null,
            isStreaming: false,
            name: $name,
        ));

        return $callback(new FakeGeneration($generationId));
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

    public function traceId(): string
    {
        return $this->traceId;
    }
}
