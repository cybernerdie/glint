<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Testing;

use Cybernerdie\Glint\Contracts\GenerationInterface;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;

final class FakeGeneration implements GenerationInterface
{
    public function __construct(private readonly string $generationId) {}

    public function tag(string $key, string $value): static
    {
        return $this;
    }

    /** @param array<string, string> $tags */
    public function tags(array $tags): static
    {
        return $this;
    }

    public function prompt(string $prompt): static
    {
        return $this;
    }

    public function options(
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?float $topP = null,
        ?bool $streaming = null,
    ): static {
        return $this;
    }

    public function finish(string $completion, int $promptTokens, int $completionTokens, string $finishReason = 'stop'): void
    {
        event(new LlmCallFinished(
            generationId: $this->generationId,
            completion: $completion,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            finishReason: $finishReason,
            durationMs: 0,
        ));
    }

    public function fail(\Throwable $e): void
    {
        event(LlmCallFailed::fromThrowable(
            generationId: $this->generationId,
            exception: $e,
            durationMs: 0,
        ));
    }

    public function generationId(): string
    {
        return $this->generationId;
    }
}
