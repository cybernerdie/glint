<?php

declare(strict_types=1);

namespace NeuronAI\Chat\Messages;

class Message
{
    public function __construct(
        private readonly ?string $content = null,
        private readonly ?Usage $usage = null,
    ) {}

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getUsage(): ?Usage
    {
        return $this->usage;
    }
}
