<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation;

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Contracts\InstrumentationDriver;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Support\GenerationFingerprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * @phpstan-type PendingGeneration array{generationId: string, startedAt: Carbon, provider: string, model: string, traceId: string|null}
 * @phpstan-type PendingTool array{spanId: string, startedAt: Carbon, toolName: string, invocationId: string}
 */
final class LaravelAiInstrumentation implements InstrumentationDriver
{
    private const AiClasses = [
        'Laravel\\Ai\\Ai',
    ];

    private const EventClassPairs = [
        'prompting' => [
            'Laravel\\Ai\\Events\\PromptingAgent',
        ],
        'streaming' => [
            'Laravel\\Ai\\Events\\StreamingAgent',
        ],
        'prompted' => [
            'Laravel\\Ai\\Events\\AgentPrompted',
        ],
        'streamed' => [
            'Laravel\\Ai\\Events\\AgentStreamed',
        ],
        'invokingTool' => [
            'Laravel\\Ai\\Events\\InvokingTool',
        ],
        'toolInvoked' => [
            'Laravel\\Ai\\Events\\ToolInvoked',
        ],
    ];

    /** @var array<string, PendingGeneration> */
    private array $pending = [];

    /** @var array<string, PendingTool> */
    private array $toolStartTimes = [];

    public function __construct(private readonly TraceContext $context) {}

    public function isAvailable(): bool
    {
        foreach (self::AiClasses as $class) {
            if (class_exists($class)) {
                return true;
            }
        }

        return false;
    }

    public function register(): void
    {
        foreach (self::EventClassPairs['prompting'] as $eventClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, fn (object $e) => app(self::class)->onPrompting($e));
            }
        }

        foreach (self::EventClassPairs['streaming'] as $eventClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, fn (object $e) => app(self::class)->onPrompting($e));
            }
        }

        foreach (self::EventClassPairs['prompted'] as $eventClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, fn (object $e) => app(self::class)->onAgentPrompted($e));
            }
        }

        foreach (self::EventClassPairs['streamed'] as $eventClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, fn (object $e) => app(self::class)->onAgentPrompted($e));
            }
        }

        foreach (self::EventClassPairs['invokingTool'] as $eventClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, fn (object $e) => app(self::class)->onInvokingTool($e));
            }
        }

        foreach (self::EventClassPairs['toolInvoked'] as $eventClass) {
            if (class_exists($eventClass)) {
                Event::listen($eventClass, fn (object $e) => app(self::class)->onToolInvoked($e));
            }
        }
    }

    public function onPrompting(object $event): void
    {
        $prompt = $this->objectValue($event, 'prompt');
        $invocationId = $this->stringValue($this->objectValue($event, 'invocationId'));

        if (! is_object($prompt) || $invocationId === null) {
            return;
        }

        $generationId = (string) Str::ulid();
        $provider = $this->resolveProvider($this->providerHint($prompt));
        $modelRaw = $this->objectValue($prompt, 'model');
        $model = is_string($modelRaw) && $modelRaw !== ''
            ? $modelRaw
            : 'unknown';
        $promptText = $this->stringValue($this->objectValue($prompt, 'prompt')) ?? '';

        $storeBody = Config::boolean('glint.recording.store_bodies', true);
        $messages = [['role' => 'user', 'content' => $promptText]];
        $isStreaming = $this->isStreamingEvent($event);
        $dedupeKey = GenerationFingerprint::make(
            provider: $provider,
            model: $model,
            messages: $messages,
            temperature: null,
            maxTokens: null,
            isStreaming: $isStreaming,
        );

        $this->pending[$invocationId] = [
            'generationId' => $generationId,
            'startedAt' => now(),
            'provider' => $provider,
            'model' => $model,
            'traceId' => $this->context->traceId(),
        ];

        event(new LlmCallStarted(
            generationId: $generationId,
            provider: $provider,
            model: $model,
            messages: $storeBody ? $messages : null,
            temperature: null,
            maxTokens: null,
            isStreaming: $isStreaming,
            traceId: $this->context->traceId(),
            parentSpanId: $this->context->activeSpanId(),
            metadata: [
                'glint_driver' => 'laravel-ai',
                'glint_dedupe_key' => $dedupeKey,
            ],
            name: $this->resolveAgentName($this->objectValue($prompt, 'agent')),
        ));
    }

    public function onAgentPrompted(object $event): void
    {
        $invocationId = $this->stringValue($this->objectValue($event, 'invocationId'));

        if ($invocationId === null) {
            return;
        }

        $pending = $this->pending[$invocationId] ?? null;

        if ($pending === null) {
            return;
        }

        unset($this->pending[$invocationId]);

        $response = $this->objectValue($event, 'response');
        $usage = is_object($response) ? $this->objectValue($response, 'usage') : null;
        $promptTokens = is_object($usage) ? $this->intValue($this->objectValue($usage, 'promptTokens')) : 0;
        $completionTokens = is_object($usage) ? $this->intValue($this->objectValue($usage, 'completionTokens')) : 0;
        $completion = is_object($response) ? $this->stringValue($this->objectValue($response, 'text')) : null;

        $storeBody = Config::boolean('glint.recording.store_bodies', true);

        event(new LlmCallFinished(
            generationId: $pending['generationId'],
            completion: $storeBody ? $completion : null,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            finishReason: 'stop',
            durationMs: (int) $pending['startedAt']->diffInMilliseconds(now()),
        ));
    }

    public function onInvokingTool(object $event): void
    {
        $toolInvocationId = $this->stringValue($this->objectValue($event, 'toolInvocationId'));
        $toolName = $this->stringValue($this->objectValue($event, 'tool'));
        $invocationId = $this->stringValue($this->objectValue($event, 'invocationId'));

        if ($toolInvocationId === null || $toolName === null || $invocationId === null) {
            return;
        }

        $this->toolStartTimes[$toolInvocationId] = [
            'spanId' => (string) Str::ulid(),
            'startedAt' => now(),
            'toolName' => $toolName,
            'invocationId' => $invocationId,
        ];
    }

    public function onToolInvoked(object $event): void
    {
        $toolInvocationId = $this->stringValue($this->objectValue($event, 'toolInvocationId'));

        if ($toolInvocationId === null) {
            return;
        }

        $toolData = $this->toolStartTimes[$toolInvocationId] ?? null;

        if ($toolData === null) {
            return;
        }

        unset($this->toolStartTimes[$toolInvocationId]);

        $pending = $this->pending[$toolData['invocationId']] ?? null;

        $traceId = ($pending !== null ? $pending['traceId'] : null)
            ?? $this->context->traceId()
            ?? $toolData['spanId'];

        $parentSpanId = $pending !== null ? $pending['generationId'] : $this->context->activeSpanId();

        event(new LlmToolCalled(
            spanId: $toolData['spanId'],
            traceId: $traceId,
            parentSpanId: $parentSpanId,
            toolName: $toolData['toolName'],
            arguments: $this->arrayValue($this->objectValue($event, 'arguments')),
            result: $this->objectValue($event, 'result'),
            durationMs: (int) $toolData['startedAt']->diffInMilliseconds(now()),
        ));
    }

    /**
     * Normalise a provider hint to a lowercase slug.
     *
     * Accepts:
     * - a FQCN like "Laravel\Ai\Providers\OpenAiProvider"
     * - a class basename like "OpenAiProvider" or "AnthropicProvider"
     * - a plain string like "openai"
     */
    private function resolveProvider(mixed $hint): string
    {
        if (is_object($hint)) {
            $hint = get_class($hint);
        }

        if (! is_string($hint) || $hint === '') {
            return 'unknown';
        }

        $basename = class_basename($hint);

        if (str_ends_with($basename, 'Provider')) {
            return strtolower(substr($basename, 0, -8));
        }

        return strtolower($hint);
    }

    private function resolveAgentName(mixed $agent): string
    {
        if (is_object($agent)) {
            return class_basename($agent);
        }

        if (is_string($agent) && $agent !== '') {
            return class_basename($agent);
        }

        return '';
    }

    private function isStreamingEvent(object $event): bool
    {
        foreach (self::EventClassPairs['streaming'] as $eventClass) {
            if (is_a($event, $eventClass)) {
                return true;
            }
        }

        return false;
    }

    private function providerHint(object $prompt): mixed
    {
        if (method_exists($prompt, 'provider')) {
            return $prompt->provider();
        }

        return $this->objectValue($prompt, 'provider');
    }

    private function objectValue(object $object, string $property): mixed
    {
        return get_object_vars($object)[$property] ?? null;
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
