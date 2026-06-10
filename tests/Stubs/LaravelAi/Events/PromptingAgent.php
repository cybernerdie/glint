<?php

declare(strict_types=1);

namespace Illuminate\AI\Events;

use Illuminate\AI\AgentPrompt;

class PromptingAgent
{
    public function __construct(
        public readonly string $invocationId,
        public readonly AgentPrompt $prompt,
    ) {}
}
