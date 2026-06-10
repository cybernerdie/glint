<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Events;

final readonly class LlmToolCalled
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $spanId,
        public string $traceId,
        public ?string $parentSpanId,
        public string $toolName,
        public array $arguments,
        public mixed $result,
        public int $durationMs,
        public array $metadata = [],
    ) {}
}
