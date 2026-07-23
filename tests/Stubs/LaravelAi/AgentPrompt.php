<?php

declare(strict_types=1);

namespace Illuminate\AI;

class AgentPrompt
{
    public function __construct(
        public readonly string $prompt = '',
        public readonly ?string $model = null,
        public readonly mixed $agent = null,
        private readonly ?string $providerClass = null,
    ) {}

    public function provider(): ?string
    {
        return $this->providerClass;
    }
}
