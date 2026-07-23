<?php

declare(strict_types=1);

namespace Laravel\Ai\Events;

use Laravel\Ai\AgentPrompt;

class PromptingAgent
{
    public function __construct(
        public readonly string $invocationId,
        public readonly AgentPrompt $prompt,
    ) {}
}
