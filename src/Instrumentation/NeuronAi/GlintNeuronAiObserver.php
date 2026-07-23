<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation\NeuronAi;

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\InferenceStart;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Observability\Events\ToolCalling;
use NeuronAI\Observability\ObserverInterface;

/**
 * @phpstan-type PendingGeneration array{generationId: string, startedAt: Carbon, provider: string, model: string}
 * @phpstan-type PendingTool array{spanId: string, startedAt: Carbon}
 */
final class GlintNeuronAiObserver implements ObserverInterface
{
    /**
     * Keyed by spl_object_id of the emitting source node — stable for the
     * duration of a single inference cycle since nodes are typically reused.
     *
     * @var array<int, PendingGeneration>
     */
    private array $pending = [];

    /**
     * Keyed by tool name. NeuronAI emits tool-calling/tool-called
     * synchronously, so one name per inference cycle is sufficient.
     *
     * @var array<string, PendingTool>
     */
    private array $toolStartTimes = [];

    /**
     * The generationId of the currently active inference, used to link tool
     * call spans back to their parent generation across different node sources.
     */
    private ?string $activeGenerationId = null;

    public function __construct(private readonly TraceContext $context) {}

    public static function make(): self
    {
        return app(self::class);
    }

    public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
    {
        $invocationKey = spl_object_id($source);

        match ($event) {
            'inference-start' => $this->onInferenceStart($source, $invocationKey, $data),
            'inference-stop' => $this->onInferenceStop($invocationKey, $data),
            'tool-calling' => $this->onToolCalling($data),
            'tool-called' => $this->onToolCalled($data),
            'error' => $this->onError($invocationKey, $data),
            default => null,
        };
    }

    public function onInferenceStart(object $source, int $invocationKey, mixed $data): void
    {
        $generationId = (string) Str::ulid();
        [$provider, $model] = $this->resolveProviderAndModel($source);

        $storeBody = Config::boolean('glint.recording.store_bodies', false);
        $messages = null;

        if ($storeBody && $data instanceof InferenceStart) {
            $content = $data->message->getContent();
            if ($content !== null) {
                $messages = [['role' => 'user', 'content' => $content]];
            }
        }

        $this->pending[$invocationKey] = [
            'generationId' => $generationId,
            'startedAt' => now(),
            'provider' => $provider,
            'model' => $model,
        ];

        $this->activeGenerationId = $generationId;

        event(new LlmCallStarted(
            generationId: $generationId,
            provider: $provider,
            model: $model,
            messages: $messages,
            temperature: null,
            maxTokens: null,
            isStreaming: false,
            traceId: $this->context->traceId(),
            parentSpanId: $this->context->activeSpanId(),
            name: class_basename($source),
        ));
    }

    public function onInferenceStop(int $invocationKey, mixed $data): void
    {
        $pending = $this->pending[$invocationKey] ?? null;

        if ($pending === null) {
            return;
        }

        unset($this->pending[$invocationKey]);
        $this->activeGenerationId = null;

        $promptTokens = 0;
        $completionTokens = 0;
        $completion = null;

        if ($data instanceof InferenceStop) {
            $usage = $data->response->getUsage();
            if ($usage !== null) {
                $promptTokens = $usage->inputTokens;
                $completionTokens = $usage->outputTokens;
            }

            if (Config::boolean('glint.recording.store_bodies', false)) {
                $completion = $data->response->getContent();
            }
        }

        event(new LlmCallFinished(
            generationId: $pending['generationId'],
            completion: $completion,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            finishReason: 'stop',
            durationMs: (int) $pending['startedAt']->diffInMilliseconds(now()),
        ));
    }

    public function onToolCalling(mixed $data): void
    {
        if (! $data instanceof ToolCalling) {
            return;
        }

        $toolName = $data->tool->getName();

        $this->toolStartTimes[$toolName] = [
            'spanId' => (string) Str::ulid(),
            'startedAt' => now(),
        ];
    }

    public function onToolCalled(mixed $data): void
    {
        if (! $data instanceof ToolCalled) {
            return;
        }

        $toolName = $data->tool->getName();
        $toolData = $this->toolStartTimes[$toolName] ?? null;

        if ($toolData === null) {
            return;
        }

        unset($this->toolStartTimes[$toolName]);

        $traceId = $this->context->traceId() ?? $toolData['spanId'];
        $parentSpanId = $this->activeGenerationId ?? $this->context->activeSpanId();

        $result = null;

        try {
            $result = $data->tool->getResult();
        } catch (\Throwable) {
        }

        event(new LlmToolCalled(
            spanId: $toolData['spanId'],
            traceId: $traceId,
            parentSpanId: $parentSpanId,
            toolName: $toolName,
            arguments: $data->tool->getInputs(),
            result: $result,
            durationMs: (int) $toolData['startedAt']->diffInMilliseconds(now()),
        ));
    }

    public function onError(int $invocationKey, mixed $data): void
    {
        $pending = $this->pending[$invocationKey] ?? null;

        if ($pending === null) {
            return;
        }

        unset($this->pending[$invocationKey]);
        $this->activeGenerationId = null;

        $exception = $data instanceof AgentError
            ? $data->exception
            : new \RuntimeException('NeuronAI agent error');

        event(LlmCallFailed::fromThrowable(
            generationId: $pending['generationId'],
            exception: $exception,
            durationMs: (int) $pending['startedAt']->diffInMilliseconds(now()),
        ));
    }

    /**
     * Extract provider name and model from the emitting source node via reflection.
     * The source is typically a ChatNode which holds a protected AIProviderInterface.
     *
     * @return array{string, string}
     */
    private function resolveProviderAndModel(object $source): array
    {
        try {
            $providerProp = new \ReflectionProperty($source, 'provider');
            $providerProp->setAccessible(true);
            $provider = $providerProp->getValue($source);

            if (! is_object($provider)) {
                return ['unknown', 'unknown'];
            }

            $providerName = strtolower(class_basename(get_class($provider)));

            try {
                $modelProp = new \ReflectionProperty($provider, 'model');
                $modelProp->setAccessible(true);
                $modelValue = $modelProp->getValue($provider);
                $model = is_string($modelValue) && $modelValue !== '' ? $modelValue : 'unknown';
            } catch (\Throwable) {
                $model = 'unknown';
            }

            return [$providerName, $model];
        } catch (\Throwable) {
            return ['unknown', 'unknown'];
        }
    }
}
