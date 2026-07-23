<?php

declare(strict_types=1);

namespace NeuronAI\Chat\Messages;

class Usage
{
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
    ) {}

    public function getTotal(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
