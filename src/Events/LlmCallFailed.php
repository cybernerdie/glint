<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Events;

final readonly class LlmCallFailed
{
    /**
     * @param  array<string, mixed>  $metadata
     *
     * Note: the exception is decomposed into plain strings so that this event
     * is safely serializable when dispatched via the async queue driver.
     * Throwable instances can contain closures, resources, or circular
     * references that cause PHP serialization to fail.
     */
    public function __construct(
        public string $generationId,
        public string $errorMessage,
        public string $errorClass,
        public int $durationMs,
        public array $metadata = [],
    ) {}

    /**
     * Convenience factory that accepts a Throwable and extracts its message
     * and class for safe serialization.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function fromThrowable(
        string $generationId,
        \Throwable $exception,
        int $durationMs,
        array $metadata = [],
    ): self {
        return new self(
            generationId: $generationId,
            errorMessage: $exception->getMessage(),
            errorClass: $exception::class,
            durationMs: $durationMs,
            metadata: $metadata,
        );
    }
}
