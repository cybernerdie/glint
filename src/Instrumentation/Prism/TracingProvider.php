<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation\Prism;

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Support\GenerationFingerprint;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\Text\Response as TextResponse;

final class TracingProvider extends Provider
{
    public function __construct(
        private readonly Provider $inner,
        private readonly TraceContext $context,
    ) {}

    public function text(TextRequest $request): TextResponse
    {
        $generationId = (string) Str::ulid();
        $startedAt = now();
        $provider = $this->resolveProviderName();
        $model = $request->model();
        $temperatureRaw = $request->temperature();
        $temperature = is_float($temperatureRaw) ? $temperatureRaw : (is_int($temperatureRaw) ? (float) $temperatureRaw : null);
        $maxTokens = $request->maxTokens();
        $topPRaw = $request->topP();
        $topP = is_float($topPRaw) ? $topPRaw : (is_int($topPRaw) ? (float) $topPRaw : null);
        $messages = $this->messages($request);
        $dedupeKey = GenerationFingerprint::make(
            provider: $provider,
            model: $model,
            messages: $messages,
            temperature: $temperature,
            maxTokens: $maxTokens,
            isStreaming: false,
        );

        event(new LlmCallStarted(
            generationId: $generationId,
            provider: $provider,
            model: $model,
            messages: Config::boolean('glint.recording.store_bodies', true) ? $messages : null,
            temperature: $temperature,
            maxTokens: $maxTokens,
            isStreaming: false,
            traceId: $this->context->traceId(),
            parentSpanId: $this->context->activeSpanId(),
            metadata: [
                'glint_driver' => 'prism',
                'glint_dedupe_key' => $dedupeKey,
            ],
            topP: $topP,
        ));

        try {
            $result = $this->callInner('text', $request);

            if (! $result instanceof TextResponse) {
                throw new \UnexpectedValueException('Prism provider text() must return a text response.');
            }

            $promptTokens = 0;
            $completionTokens = 0;
            $finishReason = 'stop';
            $completion = null;

            if (is_object($result->usage)) {
                $promptTokensRaw = property_exists($result->usage, 'promptTokens') ? $result->usage->promptTokens : 0;
                $promptTokens = is_numeric($promptTokensRaw) ? (int) $promptTokensRaw : 0;
                $completionTokensRaw = property_exists($result->usage, 'completionTokens') ? $result->usage->completionTokens : 0;
                $completionTokens = is_numeric($completionTokensRaw) ? (int) $completionTokensRaw : 0;
            }

            $finishReasonRaw = $result->finishReason;
            if (is_string($finishReasonRaw)) {
                $finishReason = $finishReasonRaw;
            } elseif ($finishReasonRaw instanceof \BackedEnum) {
                $finishReason = (string) $finishReasonRaw->value;
            } else {
                $finishReason = 'stop';
            }

            if (Config::boolean('glint.recording.store_bodies', true)) {
                $completion = $result->text;
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

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function messages(TextRequest $request): ?array
    {
        $messages = $request->messages();

        if ($messages === []) {
            return null;
        }

        return array_values(array_map(function (mixed $message): array {
            if (is_array($message)) {
                $role = is_string($message['role'] ?? null)
                    ? $message['role']
                    : (is_string($message['type'] ?? null) ? $message['type'] : 'message');

                return [
                    'role' => $role,
                    'content' => $this->content($message['content'] ?? ''),
                ];
            }

            if ($message instanceof Arrayable) {
                /** @var array<string, mixed> $data */
                $data = $message->toArray();
                $role = is_string($data['role'] ?? null)
                    ? $data['role']
                    : (is_string($data['type'] ?? null) ? $data['type'] : 'message');

                return [
                    'role' => $role,
                    'content' => $this->content($data['content'] ?? ''),
                ];
            }

            return [
                'role' => 'message',
                'content' => $this->content($message),
            ];
        }, $messages));
    }

    private function content(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return $value;
    }
}
