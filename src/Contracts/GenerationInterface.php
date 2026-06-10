<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Contracts;

interface GenerationInterface
{
    public function tag(string $key, string $value): static;

    /** @param array<string, string> $tags */
    public function tags(array $tags): static;

    public function finish(string $completion, int $promptTokens, int $completionTokens, string $finishReason = 'stop'): void;

    public function fail(\Throwable $e): void;

    public function generationId(): ?string;
}
