<?php

declare(strict_types=1);

namespace Prism\Prism\Providers;

class Anthropic
{
    public function text(mixed $request): object
    {
        return (object) [
            'text' => 'Hello from Anthropic',
            'usage' => (object) ['promptTokens' => 10, 'completionTokens' => 5],
            'finishReason' => 'stop',
        ];
    }
}
