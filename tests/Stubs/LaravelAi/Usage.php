<?php

declare(strict_types=1);

namespace Laravel\Ai;

class Usage
{
    public function __construct(
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
    ) {}
}
