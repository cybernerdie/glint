<?php

declare(strict_types=1);

namespace Prism\Prism\Providers;

use Prism\Prism\Text\Response;

class Anthropic extends Provider
{
    public function text(mixed $request): Response
    {
        return new Response(
            text: 'Hello from Anthropic',
            usage: (object) ['promptTokens' => 10, 'completionTokens' => 5],
            finishReason: 'stop',
        );
    }
}
