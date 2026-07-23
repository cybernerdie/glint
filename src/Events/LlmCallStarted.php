<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Events;

final readonly class LlmCallStarted
{
    /**
     * @param  array<int, mixed>|null  $messages
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $generationId,
        public string $provider,
        public string $model,
        public ?array $messages,
        public ?float $temperature,
        public ?int $maxTokens,
        public bool $isStreaming,
        public ?string $traceId = null,
        public ?string $parentSpanId = null,
        public array $metadata = [],
        public string $name = '',
        public ?float $topP = null,
    ) {}
}
