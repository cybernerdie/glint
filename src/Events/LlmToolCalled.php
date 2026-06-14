<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Events;

final readonly class LlmToolCalled
{
    /** @var string|int|float|bool|array<array-key, mixed>|null */
    public string|int|float|bool|array|null $result;

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
        mixed $result,
        public int $durationMs,
        public array $metadata = [],
    ) {
        $this->result = $this->normalizeResult($result);
    }

    /**
     * Coerce arbitrary tool results into a queue-serializable value so the
     * recording job never fails on closures, resources, or rich objects.
     */
    /** @return string|int|float|bool|array<array-key, mixed>|null */
    private function normalizeResult(mixed $result): string|int|float|bool|array|null
    {
        if ($result === null || is_scalar($result) || is_array($result)) {
            return $result;
        }

        $decoded = json_decode((string) json_encode($result), true);

        return is_array($decoded) ? $decoded : null;
    }
}
