<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Filtering;

final readonly class FilterEntry
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $provider,
        public string $model,
        public ?string $traceId,
        public array $metadata,
    ) {}
}
