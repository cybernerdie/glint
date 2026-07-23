<?php

declare(strict_types=1);

namespace Laravel\Ai\Events;

class InvokingTool
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $invocationId,
        public readonly string $toolInvocationId,
        public readonly mixed $agent,
        public readonly string $tool,
        public readonly array $arguments,
    ) {}
}
