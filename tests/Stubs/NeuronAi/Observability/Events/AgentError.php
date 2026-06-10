<?php

declare(strict_types=1);

namespace NeuronAI\Observability\Events;

use Throwable;

class AgentError
{
    public function __construct(
        public readonly Throwable $exception,
        public readonly bool $unhandled = true,
    ) {}
}
