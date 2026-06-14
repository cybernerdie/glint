<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Events;

final readonly class GlintDataCleared
{
    /** @param array<string, int> $deletedByTable */
    public function __construct(
        public array $deletedByTable,
    ) {}
}
