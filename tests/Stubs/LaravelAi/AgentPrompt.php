<?php

declare(strict_types=1);

namespace Laravel\Ai;

class AgentPrompt
{
    public function __construct(
        public readonly string $prompt = '',
        public readonly ?string $model = null,
        public readonly mixed $agent = null,
        private readonly mixed $providerClass = null,
    ) {}

    public function provider(): mixed
    {
        return $this->providerClass;
    }
}
