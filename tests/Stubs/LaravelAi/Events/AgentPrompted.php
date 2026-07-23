<?php

declare(strict_types=1);

namespace Laravel\Ai\Events;

use Laravel\Ai\AgentPrompt;
use Laravel\Ai\AgentResponse;

class AgentPrompted
{
    public function __construct(
        public readonly string $invocationId,
        public readonly AgentPrompt $prompt,
        public readonly AgentResponse $response,
    ) {}
}
