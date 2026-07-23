<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Null;

use Cybernerdie\Glint\Contracts\GenerationInterface;

final class NullGeneration implements GenerationInterface
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

    public function finish(string $completion, int $promptTokens, int $completionTokens, string $finishReason = 'stop'): void {}

    public function fail(\Throwable $e): void {}

    public function generationId(): ?string
    {
        return null;
    }
}
