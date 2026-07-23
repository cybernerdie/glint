<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation\Prism;

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

final class TracingProvider
{
    public function __construct(
        private readonly object $inner,
        private readonly TraceContext $context,
    ) {}

    public function text(mixed $request): mixed
    {
        $generationId = (string) Str::ulid();
        $startedAt = now();
        $provider = $this->resolveProviderName();
        $modelRaw = is_object($request) && property_exists($request, 'model') ? $request->model : 'unknown';
        $model = is_string($modelRaw) ? $modelRaw : 'unknown';
        $temperatureRaw = is_object($request) && property_exists($request, 'temperature') ? $request->temperature : null;
        $temperature = is_float($temperatureRaw) ? $temperatureRaw : (is_int($temperatureRaw) ? (float) $temperatureRaw : null);
        $maxTokensRaw = is_object($request) && property_exists($request, 'maxTokens') ? $request->maxTokens : null;
        $maxTokens = is_int($maxTokensRaw) ? $maxTokensRaw : null;
        $messagesRaw = is_object($request) && property_exists($request, 'messages') ? $request->messages : null;
        $messages = is_array($messagesRaw) ? array_values($messagesRaw) : null;

        event(new LlmCallStarted(
            generationId: $generationId,
            provider: $provider,
            model: $model,
            messages: Config::boolean('glint.recording.store_bodies', false) ? $messages : null,
            temperature: $temperature,
            maxTokens: $maxTokens,
            isStreaming: false,
            traceId: $this->context->traceId(),
            parentSpanId: $this->context->activeSpanId(),
        ));

        try {
            $result = $this->callInner('text', $request);

            $promptTokens = 0;
            $completionTokens = 0;
            $finishReason = 'stop';
            $completion = null;

            if (is_object($result)) {
                if (property_exists($result, 'usage') && is_object($result->usage)) {
                    $promptTokensRaw = property_exists($result->usage, 'promptTokens') ? $result->usage->promptTokens : 0;
                    $promptTokens = is_numeric($promptTokensRaw) ? (int) $promptTokensRaw : 0;
                    $completionTokensRaw = property_exists($result->usage, 'completionTokens') ? $result->usage->completionTokens : 0;
                    $completionTokens = is_numeric($completionTokensRaw) ? (int) $completionTokensRaw : 0;
                }

                if (property_exists($result, 'finishReason')) {
                    $finishReasonRaw = $result->finishReason;
                    if (is_string($finishReasonRaw)) {
                        $finishReason = $finishReasonRaw;
                    } elseif ($finishReasonRaw instanceof \BackedEnum) {
                        $finishReason = (string) $finishReasonRaw->value;
                    } else {
                        $finishReason = 'stop';
                    }
                }

                if (Config::boolean('glint.recording.store_bodies', false) && property_exists($result, 'text')) {
                    $textRaw = $result->text;
                    $completion = is_string($textRaw) ? $textRaw : null;
                }
            }

            event(new LlmCallFinished(
                generationId: $generationId,
                completion: $completion,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                finishReason: $finishReason,
                durationMs: (int) $startedAt->diffInMilliseconds(now()),
            ));

            return $result;
        } catch (\Throwable $e) {
            event(LlmCallFailed::fromThrowable(
                generationId: $generationId,
                exception: $e,
                durationMs: (int) $startedAt->diffInMilliseconds(now()),
            ));
            throw $e;
        }
    }

    /** @param array<int, mixed> $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->callInner($name, ...$arguments);
    }

    private function callInner(string $method, mixed ...$args): mixed
    {
        if (method_exists($this->inner, $method)) {
            return $this->inner->$method(...$args);
        }

        throw new \BadMethodCallException("Call to undefined method {$method}() on ".get_class($this->inner));
    }

    private function resolveProviderName(): string
    {
        $class = get_class($this->inner);
        $parts = explode('\\', $class);
        $shortName = strtolower(end($parts));

        $providerMap = [
            'anthropic' => 'anthropic',
            'anthropicprovider' => 'anthropic',
            'openai' => 'openai',
            'openaiprovider' => 'openai',
            'gemini' => 'gemini',
            'geminiprovider' => 'gemini',
            'mistral' => 'mistral',
            'mistralprovider' => 'mistral',
            'groq' => 'groq',
            'groqprovider' => 'groq',
            'ollama' => 'ollama',
            'ollamaprovider' => 'ollama',
            'xai' => 'xai',
            'xaiprovider' => 'xai',
        ];

        return $providerMap[$shortName] ?? $shortName;
    }
}
