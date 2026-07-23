<?php

declare(strict_types=1);

namespace Laravel\Ai;

class AgentResponse
{
    public function __construct(
        public readonly string $text = '',
        public readonly ?Usage $usage = null,
        public readonly ?Meta $meta = null,
    ) {}
}
