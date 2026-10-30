<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation\LaravelAi;

use Closure;
use Cybernerdie\Glint\Instrumentation\LaravelAiInstrumentation;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\Error;

final readonly class GlintPromptMiddleware
{
    public function __construct(private LaravelAiInstrumentation $instrumentation) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        try {
            $response = $next($prompt);

            if (! $response instanceof StreamableAgentResponse) {
                return $response;
            }

            $stream = new StreamableAgentResponse(
                $response->invocationId,
                function () use ($response, $prompt) {
                    $streamError = null;

                    try {
                        foreach ($response as $event) {
                            if ($event instanceof Error && ! $event->recoverable) {
                                $streamError = $event;
                            }

                            yield $event;
                        }
                    } catch (\Throwable $exception) {
                        $this->instrumentation->onPromptFailed($prompt, $exception);

                        throw $exception;
                    }

                    if ($streamError instanceof Error) {
                        $this->instrumentation->onStreamFailed($prompt, $streamError->message, $streamError->type);
                    }
                },
                new Meta(model: $prompt->model),
            );

            $response->then(fn (StreamedAgentResponse $completed): StreamableAgentResponse => $stream->adoptStateFrom($completed));

            return $stream;
        } catch (\Throwable $exception) {
            $this->instrumentation->onPromptFailed($prompt, $exception);

            throw $exception;
        }
    }
}
