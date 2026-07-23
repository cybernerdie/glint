<?php

declare(strict_types=1);

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Instrumentation\LaravelAiInstrumentation;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\AgentPrompt;
use Laravel\Ai\AgentResponse;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\InvokingTool;
use Laravel\Ai\Events\PromptingAgent;
use Laravel\Ai\Events\StreamingAgent;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Meta;
use Laravel\Ai\Usage;

it('isAvailable returns true when the AI stub class exists', function () {
    $instrumentation = new LaravelAiInstrumentation(new TraceContext);

    expect($instrumentation->isAvailable())->toBeTrue();
});

it('register attaches event listeners without throwing', function () {
    $instrumentation = new LaravelAiInstrumentation(new TraceContext);

    expect(fn () => $instrumentation->register())->not->toThrow(Throwable::class);
});

it('onPrompting fires LlmCallStarted with resolved provider and model', function () {
    Event::fake();

    $context = new TraceContext;
    $context->openTrace('trace-xyz');

    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(
        prompt: 'Summarise this document',
        model: 'gpt-4o',
        agent: null,
        providerClass: 'OpenAiProvider',
    );

    $instrumentation->onPrompting(new PromptingAgent('inv-001', $prompt));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->provider === 'openai'
            && $e->model === 'gpt-4o'
            && $e->traceId === 'trace-xyz'
            && $e->isStreaming === false;
    });
});

it('onPrompting sets isStreaming true for StreamingAgent events', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'Stream this', model: 'claude-3-5-sonnet');

    $instrumentation->onPrompting(new StreamingAgent('inv-002', $prompt));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->isStreaming === true;
    });
});

it('onPrompting stores messages when store_bodies is true', function () {
    Event::fake();
    config()->set('glint.recording.store_bodies', true);

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'What is AI?', model: 'gpt-4o');

    $instrumentation->onPrompting(new PromptingAgent('inv-003', $prompt));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->messages === [['role' => 'user', 'content' => 'What is AI?']];
    });
});

it('onPrompting uses unknown when model is null', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'Hello', model: null);

    $instrumentation->onPrompting(new PromptingAgent('inv-004', $prompt));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->model === 'unknown';
    });
});

it('onPrompting sets agent class name as the generation name', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $agentStub = new class {};
    $prompt = new AgentPrompt(prompt: 'Hello', model: 'gpt-4o', agent: $agentStub);

    $instrumentation->onPrompting(new PromptingAgent('inv-005', $prompt));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return is_string($e->name);
    });
});

it('onAgentPrompted fires LlmCallFinished for a tracked invocation', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'Hello', model: 'gpt-4o', providerClass: 'OpenAiProvider');
    $instrumentation->onPrompting(new PromptingAgent('inv-010', $prompt));

    Event::assertDispatched(LlmCallStarted::class);

    $response = new AgentResponse(
        text: 'World',
        usage: new Usage(promptTokens: 12, completionTokens: 8),
        meta: new Meta(provider: 'openai', model: 'gpt-4o'),
    );

    $instrumentation->onAgentPrompted(new AgentPrompted('inv-010', $prompt, $response));

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->promptTokens === 12
            && $e->completionTokens === 8
            && $e->finishReason === 'stop'
            && $e->completion === 'World';
    });
});

it('onAgentPrompted stores completion text when store_bodies is true', function () {
    Event::fake();
    config()->set('glint.recording.store_bodies', true);

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'Hello', model: 'gpt-4o');
    $instrumentation->onPrompting(new PromptingAgent('inv-011', $prompt));

    $response = new AgentResponse(
        text: 'Secret answer',
        usage: new Usage(promptTokens: 5, completionTokens: 3),
        meta: new Meta,
    );

    $instrumentation->onAgentPrompted(new AgentPrompted('inv-011', $prompt, $response));

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->completion === 'Secret answer';
    });
});

it('onAgentPrompted does nothing for untracked invocation IDs', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'Hello', model: 'gpt-4o');
    $response = new AgentResponse(text: 'Hi', usage: new Usage, meta: new Meta);

    $instrumentation->onAgentPrompted(new AgentPrompted('inv-unknown', $prompt, $response));

    Event::assertNotDispatched(LlmCallFinished::class);
});

it('onAgentPrompted works for AgentStreamed events', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'Stream', model: 'claude-3-5-sonnet');
    $instrumentation->onPrompting(new StreamingAgent('inv-012', $prompt));

    $response = new AgentResponse(
        text: 'Streamed text',
        usage: new Usage(promptTokens: 20, completionTokens: 10),
        meta: new Meta,
    );

    $instrumentation->onAgentPrompted(new AgentStreamed('inv-012', $prompt, $response));

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->promptTokens === 20 && $e->completionTokens === 10;
    });
});

it('onToolInvoked fires LlmToolCalled with correct tool name and duration', function () {
    Event::fake();

    $context = new TraceContext;
    $context->openTrace('trace-tool');
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'Use the tool', model: 'gpt-4o');
    $instrumentation->onPrompting(new PromptingAgent('inv-020', $prompt));

    $instrumentation->onInvokingTool(new InvokingTool(
        invocationId: 'inv-020',
        toolInvocationId: 'tool-001',
        agent: null,
        tool: 'search_web',
        arguments: ['query' => 'Laravel'],
    ));

    $instrumentation->onToolInvoked(new ToolInvoked(
        invocationId: 'inv-020',
        toolInvocationId: 'tool-001',
        agent: null,
        tool: 'search_web',
        arguments: ['query' => 'Laravel'],
        result: ['results' => ['Laravel docs']],
    ));

    Event::assertDispatched(LlmToolCalled::class, function (LlmToolCalled $e) {
        return $e->toolName === 'search_web'
            && $e->arguments === ['query' => 'Laravel']
            && $e->result === ['results' => ['Laravel docs']]
            && $e->durationMs >= 0;
    });
});

it('onToolInvoked does nothing for untracked tool invocation IDs', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $instrumentation->onToolInvoked(new ToolInvoked(
        invocationId: 'inv-999',
        toolInvocationId: 'tool-999',
        agent: null,
        tool: 'missing_tool',
        arguments: [],
        result: null,
    ));

    Event::assertNotDispatched(LlmToolCalled::class);
});

it('onToolInvoked uses spanId as traceId fallback when no trace context is open', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'No trace', model: 'gpt-4o');
    $instrumentation->onPrompting(new PromptingAgent('inv-030', $prompt));

    $instrumentation->onInvokingTool(new InvokingTool(
        invocationId: 'inv-030',
        toolInvocationId: 'tool-030',
        agent: null,
        tool: 'get_time',
        arguments: [],
    ));

    $instrumentation->onToolInvoked(new ToolInvoked(
        invocationId: 'inv-030',
        toolInvocationId: 'tool-030',
        agent: null,
        tool: 'get_time',
        arguments: [],
        result: '12:00',
    ));

    Event::assertDispatched(LlmToolCalled::class, function (LlmToolCalled $e) {
        return $e->toolName === 'get_time' && $e->traceId !== '';
    });
});

it('resolves FQCN provider strings correctly', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(
        prompt: 'Hello',
        model: 'claude-3-5-sonnet',
        providerClass: 'Laravel\\Ai\\Providers\\AnthropicProvider',
    );

    $instrumentation->onPrompting(new PromptingAgent('inv-fqcn', $prompt));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->provider === 'anthropic';
    });
});

it('resolves plain lowercase provider strings correctly', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'Hello', model: 'gemini-pro', providerClass: 'gemini');

    $instrumentation->onPrompting(new PromptingAgent('inv-plain', $prompt));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->provider === 'gemini';
    });
});

it('onPrompting sets messages to null when store_bodies is false', function () {
    Event::fake();
    config()->set('glint.recording.store_bodies', false);

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'What is AI?', model: 'gpt-4o');

    $instrumentation->onPrompting(new PromptingAgent('inv-nobody', $prompt));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->messages === null;
    });
});

it('resolveAgentName returns class_basename for a string agent name', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(
        prompt: 'Hello',
        model: 'gpt-4o',
        agent: 'App\\Agents\\SummaryAgent',
    );

    $instrumentation->onPrompting(new PromptingAgent('inv-str-agent', $prompt));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->name === 'SummaryAgent';
    });
});

it('uses unknown provider when provider hint is null', function () {
    Event::fake();

    $context = new TraceContext;
    $instrumentation = new LaravelAiInstrumentation($context);

    $prompt = new AgentPrompt(prompt: 'Hello', model: 'gpt-4o', providerClass: null);

    $instrumentation->onPrompting(new PromptingAgent('inv-null-prov', $prompt));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->provider === 'unknown';
    });
});
