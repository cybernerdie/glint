<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Testing;

use Cybernerdie\Glint\Enums\RecordStatus;

final class CapturedGeneration
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $id,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $name,
        public readonly RecordStatus $status,
        public readonly ?string $completion,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly float $costUsd,
        public readonly ?string $finishReason,
        public readonly ?string $errorMessage,
        public readonly array $metadata,
    ) {}
}
