<?php

declare(strict_types=1);

namespace Illuminate\AI\Events;

class ToolInvoked extends InvokingTool
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        string $invocationId,
        string $toolInvocationId,
        mixed $agent,
        string $tool,
        array $arguments,
        public readonly mixed $result,
    ) {
        parent::__construct($invocationId, $toolInvocationId, $agent, $tool, $arguments);
    }
}
