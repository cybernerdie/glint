<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Events;

final readonly class LlmCallFinished
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $generationId,
        public ?string $completion,
        public int $promptTokens,
        public int $completionTokens,
        public string $finishReason,
        public int $durationMs,
        public array $metadata = [],
    ) {}
}
