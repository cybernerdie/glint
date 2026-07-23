<?php

declare(strict_types=1);

namespace Illuminate\AI\Events;

use Illuminate\AI\AgentPrompt;
use Illuminate\AI\AgentResponse;

class AgentPrompted
{
    public function __construct(
        public readonly string $invocationId,
        public readonly AgentPrompt $prompt,
        public readonly AgentResponse $response,
    ) {}
}
