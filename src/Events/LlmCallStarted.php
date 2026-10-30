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

    public function withTraceId(string $traceId): self
    {
        return new self(
            generationId: $this->generationId,
            provider: $this->provider,
            model: $this->model,
            messages: $this->messages,
            temperature: $this->temperature,
            maxTokens: $this->maxTokens,
            isStreaming: $this->isStreaming,
            traceId: $traceId,
            parentSpanId: $this->parentSpanId,
            metadata: $this->metadata,
            name: $this->name,
            topP: $this->topP,
        );
    }
}
