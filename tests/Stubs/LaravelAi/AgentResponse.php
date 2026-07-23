<?php

declare(strict_types=1);

namespace Illuminate\AI;

class AgentResponse
{
    public function __construct(
        public readonly string $text = '',
        public readonly ?Usage $usage = null,
        public readonly ?Meta $meta = null,
    ) {}
}
