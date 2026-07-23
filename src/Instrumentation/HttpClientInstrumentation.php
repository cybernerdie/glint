<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation;

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Contracts\InstrumentationDriver;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Instrumentation\Http\ResponseParser;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class HttpClientInstrumentation implements InstrumentationDriver
{
    /** @var array<string, array{generationId: string, startedAt: Carbon, provider: string}> */
    private array $pending = [];

    /** @var array<string, string> */
    private array $hashMap = [];

    public function __construct(private readonly TraceContext $context) {}

    public function isAvailable(): bool
    {
        return true;
    }

    public function register(): void
    {
        // Resolve via app() at event time so Octane's per-request scoped instance is used.
        Event::listen(RequestSending::class, fn (RequestSending $e) => app(self::class)->onRequestSending($e));
        Event::listen(ResponseReceived::class, fn (ResponseReceived $e) => app(self::class)->onResponseReceived($e));
        Event::listen(ConnectionFailed::class, fn (ConnectionFailed $e) => app(self::class)->onConnectionFailed($e));
    }

    public function onRequestSending(RequestSending $event): void
    {
        $host = (string) parse_url($event->request->url(), PHP_URL_HOST);
        $llmHostsRaw = Config::get('glint.llm_hosts', []);
        $llmHosts = is_array($llmHostsRaw) ? $llmHostsRaw : [];
        $providerRaw = $llmHosts[$host] ?? null;
        $provider = is_string($providerRaw) ? $providerRaw : null;

        if ($provider === null) {
            return;
        }

        $generationId = (string) Str::ulid();
        $correlationId = (string) Str::uuid();
        $startedAt = now();

        $this->pending[$correlationId] = [
            'generationId' => $generationId,
            'startedAt' => $startedAt,
            'provider' => $provider,
        ];

        // spl_object_id() returns the internal object handle, which PHP MAY reuse
        // after the original object is garbage-collected. In practice this is safe
        // within a single request lifecycle, but is a known PHP limitation.
        $hashKey = 'hash_'.spl_object_id($event->request);

        // If RequestSending fires twice for the same request object (e.g. on retry),
        // the old pending entry would be orphaned. Clean it up first so we don't leak.
        $staleCorrelationId = $this->hashMap[$hashKey] ?? null;

        if ($staleCorrelationId !== null && isset($this->pending[$staleCorrelationId])) {
            unset($this->pending[$staleCorrelationId]);
        }

        $this->hashMap[$hashKey] = $correlationId;

        $body = [];

        try {
            $rawBody = $event->request->body();
            if (! empty($rawBody)) {
                $decoded = json_decode($rawBody, true);
                $body = is_array($decoded) ? $decoded : [];
            }
        } catch (\Throwable) {
        }

        $modelRaw = $body['model'] ?? 'unknown';
        $model = is_string($modelRaw) ? $modelRaw : 'unknown';
        $messagesRaw = isset($body['messages']) && is_array($body['messages']) ? $body['messages'] : null;
        $messages = Config::boolean('glint.recording.store_bodies', false) && $messagesRaw !== null
            ? array_values($messagesRaw)
            : null;
        $temperatureRaw = $body['temperature'] ?? null;
        $temperature = is_float($temperatureRaw) ? $temperatureRaw : (is_int($temperatureRaw) ? (float) $temperatureRaw : null);
        $maxTokensRaw = $body['max_tokens'] ?? null;
        $maxTokens = is_int($maxTokensRaw) ? $maxTokensRaw : null;

        event(new LlmCallStarted(
            generationId: $generationId,
            provider: $provider,
            model: $model,
            messages: $messages,
            temperature: $temperature,
            maxTokens: $maxTokens,
            isStreaming: (bool) ($body['stream'] ?? false),
            traceId: $this->context->traceId(),
            parentSpanId: $this->context->activeSpanId(),
        ));
    }

    public function onResponseReceived(ResponseReceived $event): void
    {
        $hashKey = 'hash_'.spl_object_id($event->request);
        $correlationId = $this->hashMap[$hashKey] ?? null;

        if ($correlationId === null) {
            return;
        }

        $pending = $this->pending[$correlationId] ?? null;

        if ($pending === null) {
            return;
        }

        unset($this->pending[$correlationId], $this->hashMap[$hashKey]);

        if ($event->response->failed()) {
            event(LlmCallFailed::fromThrowable(
                generationId: $pending['generationId'],
                exception: new \RuntimeException('LLM API returned HTTP '.$event->response->status()),
                durationMs: (int) $pending['startedAt']->diffInMilliseconds(now()),
            ));

            return;
        }

        $body = [];

        try {
            $rawJson = $event->response->json();
            $body = is_array($rawJson) ? $rawJson : [];
        } catch (\Throwable) {
        }

        $parser = new ResponseParser;

        event(new LlmCallFinished(
            generationId: $pending['generationId'],
            completion: Config::boolean('glint.recording.store_bodies', false)
                ? $parser->completion($pending['provider'], $body)
                : null,
            promptTokens: $parser->promptTokens($pending['provider'], $body),
            completionTokens: $parser->completionTokens($pending['provider'], $body),
            finishReason: $parser->finishReason($pending['provider'], $body),
            durationMs: intval($pending['startedAt']->diffInMilliseconds(now())),
        ));
    }

    public function onConnectionFailed(ConnectionFailed $event): void
    {
        $hashKey = 'hash_'.spl_object_id($event->request);
        $correlationId = $this->hashMap[$hashKey] ?? null;

        if ($correlationId === null) {
            return;
        }

        $pending = $this->pending[$correlationId] ?? null;

        if ($pending === null) {
            return;
        }

        unset($this->pending[$correlationId], $this->hashMap[$hashKey]);

        event(LlmCallFailed::fromThrowable(
            generationId: $pending['generationId'],
            exception: new \RuntimeException('HTTP connection failed to LLM endpoint'),
            durationMs: (int) $pending['startedAt']->diffInMilliseconds(now()),
        ));
    }
}
