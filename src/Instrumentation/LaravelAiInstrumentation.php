<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Instrumentation;

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Contracts\InstrumentationDriver;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Illuminate\AI\AI;
use Illuminate\AI\Events\AgentPrompted;
use Illuminate\AI\Events\AgentStreamed;
use Illuminate\AI\Events\InvokingTool;
use Illuminate\AI\Events\PromptingAgent;
use Illuminate\AI\Events\StreamingAgent;
use Illuminate\AI\Events\ToolInvoked;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class LaravelAiInstrumentation implements InstrumentationDriver
{
    /**
     * @var array<string, array{generationId: string, startedAt: Carbon, provider: string, model: string, traceId: string|null}>
     */
    private array $pending = [];

    /**
     * @var array<string, array{spanId: string, startedAt: Carbon, toolName: string, invocationId: string}>
     */
    private array $toolStartTimes = [];

    public function __construct(private readonly TraceContext $context) {}

    public function isAvailable(): bool
    {
        return class_exists(AI::class);
    }

    public function register(): void
    {
        Event::listen(PromptingAgent::class, fn (PromptingAgent $e) => app(self::class)->onPrompting($e));
        Event::listen(StreamingAgent::class, fn (StreamingAgent $e) => app(self::class)->onPrompting($e));
        Event::listen(AgentPrompted::class, fn (AgentPrompted $e) => app(self::class)->onAgentPrompted($e));
        Event::listen(AgentStreamed::class, fn (AgentStreamed $e) => app(self::class)->onAgentPrompted($e));
        Event::listen(InvokingTool::class, fn (InvokingTool $e) => app(self::class)->onInvokingTool($e));
        Event::listen(ToolInvoked::class, fn (ToolInvoked $e) => app(self::class)->onToolInvoked($e));
    }

    public function onPrompting(PromptingAgent $event): void
    {
        $generationId = (string) Str::ulid();
        $provider = $this->resolveProvider($event->prompt->provider());
        $model = is_string($event->prompt->model) && $event->prompt->model !== ''
            ? $event->prompt->model
            : 'unknown';

        $storeBody = Config::boolean('glint.recording.store_bodies', false);
        $messages = $storeBody
            ? [['role' => 'user', 'content' => $event->prompt->prompt]]
            : null;

        $this->pending[$event->invocationId] = [
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
            messages: $messages,
            temperature: null,
            maxTokens: null,
            isStreaming: $event instanceof StreamingAgent,
            traceId: $this->context->traceId(),
            parentSpanId: $this->context->activeSpanId(),
            name: $this->resolveAgentName($event->prompt->agent),
        ));
    }

    public function onAgentPrompted(AgentPrompted $event): void
    {
        $pending = $this->pending[$event->invocationId] ?? null;

        if ($pending === null) {
            return;
        }

        unset($this->pending[$event->invocationId]);

        $usage = $event->response->usage;
        $promptTokens = $usage !== null ? $usage->promptTokens : 0;
        $completionTokens = $usage !== null ? $usage->completionTokens : 0;

        $storeBody = Config::boolean('glint.recording.store_bodies', false);

        event(new LlmCallFinished(
            generationId: $pending['generationId'],
            completion: $storeBody ? $event->response->text : null,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            finishReason: 'stop',
            durationMs: (int) $pending['startedAt']->diffInMilliseconds(now()),
        ));
    }

    public function onInvokingTool(InvokingTool $event): void
    {
        $this->toolStartTimes[$event->toolInvocationId] = [
            'spanId' => (string) Str::ulid(),
            'startedAt' => now(),
            'toolName' => $event->tool,
            'invocationId' => $event->invocationId,
        ];
    }

    public function onToolInvoked(ToolInvoked $event): void
    {
        $toolData = $this->toolStartTimes[$event->toolInvocationId] ?? null;

        if ($toolData === null) {
            return;
        }

        unset($this->toolStartTimes[$event->toolInvocationId]);

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
            arguments: $event->arguments,
            result: $event->result,
            durationMs: (int) $toolData['startedAt']->diffInMilliseconds(now()),
        ));
    }

    /**
     * Normalise a provider hint to a lowercase slug.
     *
     * Accepts:
     * - a FQCN like "Illuminate\AI\Providers\OpenAiProvider"
     * - a class basename like "OpenAiProvider" or "AnthropicProvider"
     * - a plain string like "openai"
     */
    private function resolveProvider(mixed $hint): string
    {
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
}
