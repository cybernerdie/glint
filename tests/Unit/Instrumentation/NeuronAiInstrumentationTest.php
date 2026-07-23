<?php

declare(strict_types=1);

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Events\LlmToolCalled;
use Cybernerdie\Glint\Instrumentation\NeuronAi\GlintNeuronAiObserver;
use Cybernerdie\Glint\Instrumentation\NeuronAiInstrumentation;
use Illuminate\Support\Facades\Event;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Observability\EventBus;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\InferenceStart;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Observability\Events\ToolCalling;
use NeuronAI\Tools\ToolInterface;

function makeSourceNode(string $providerClass = 'openai', string $model = 'gpt-4o'): object
{
    return new class($providerClass, $model)
    {
        public function __construct(
            protected string $providerName,
            protected string $model,
        ) {}
    };
}

function makeTool(string $name, string $result = 'ok', array $inputs = []): ToolInterface
{
    return new class($name, $result, $inputs) implements ToolInterface
    {
        public function __construct(
            private string $toolName,
            private string $toolResult,
            private array $toolInputs,
        ) {}

        public function getName(): string
        {
            return $this->toolName;
        }

        public function getDescription(): ?string
        {
            return null;
        }

        public function getInputs(): array
        {
            return $this->toolInputs;
        }

        public function getResult(): string
        {
            return $this->toolResult;
        }
    };
}

it('isAvailable returns true when the NeuronAI Agent stub class exists', function () {
    $instrumentation = new NeuronAiInstrumentation;

    expect($instrumentation->isAvailable())->toBeTrue();
});

it('register attaches a global observer to the NeuronAI EventBus', function () {
    EventBus::clear();

    $instrumentation = new NeuronAiInstrumentation;
    $instrumentation->register();

    expect(fn () => EventBus::emit('unknown-event', new stdClass))->not->toThrow(Throwable::class);
});

it('onEvent inference-start fires LlmCallStarted', function () {
    Event::fake();

    $context = new TraceContext;
    $context->openTrace('trace-neuron');
    $observer = new GlintNeuronAiObserver($context);

    $source = makeSourceNode();
    $message = new Message('What is the capital of France?');
    $observer->onEvent('inference-start', $source, new InferenceStart($message));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->traceId === 'trace-neuron'
            && $e->isStreaming === false
            && is_string($e->generationId);
    });
});

it('onEvent inference-stop fires LlmCallFinished with token counts', function () {
    Event::fake();

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $source = makeSourceNode();
    $inputMessage = new Message('Hello');
    $observer->onEvent('inference-start', $source, new InferenceStart($inputMessage));

    $responseMessage = new Message(
        'Bonjour',
        new Usage(inputTokens: 15, outputTokens: 5),
    );
    $observer->onEvent('inference-stop', $source, new InferenceStop($inputMessage, $responseMessage));

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->promptTokens === 15
            && $e->completionTokens === 5
            && $e->finishReason === 'stop'
            && $e->completion === 'Bonjour';
    });
});

it('onEvent inference-stop does nothing for untracked source', function () {
    Event::fake();

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $source = makeSourceNode();
    $inputMessage = new Message('Hello');
    $responseMessage = new Message('Hi', new Usage(inputTokens: 5, outputTokens: 3));

    $observer->onEvent('inference-stop', $source, new InferenceStop($inputMessage, $responseMessage));

    Event::assertNotDispatched(LlmCallFinished::class);
});

it('onEvent error fires LlmCallFailed', function () {
    Event::fake();

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $source = makeSourceNode();
    $observer->onEvent('inference-start', $source, new InferenceStart(new Message('Hello')));

    $observer->onEvent('error', $source, new AgentError(new RuntimeException('API timeout')));

    Event::assertDispatched(LlmCallFailed::class, function (LlmCallFailed $e) {
        return str_contains($e->errorMessage, 'API timeout');
    });
});

it('onEvent error does nothing for untracked source', function () {
    Event::fake();

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $source = makeSourceNode();
    $observer->onEvent('error', $source, new AgentError(new RuntimeException('Oops')));

    Event::assertNotDispatched(LlmCallFailed::class);
});

it('onEvent tool-calling and tool-called fires LlmToolCalled', function () {
    Event::fake();

    $context = new TraceContext;
    $context->openTrace('trace-tool');
    $observer = new GlintNeuronAiObserver($context);

    $source = makeSourceNode();
    $observer->onEvent('inference-start', $source, new InferenceStart(new Message('Use the tool')));

    $toolSource = new stdClass;
    $tool = makeTool('search_web', 'result: Laravel', ['query' => 'Laravel']);

    $observer->onEvent('tool-calling', $toolSource, new ToolCalling($tool));
    $observer->onEvent('tool-called', $toolSource, new ToolCalled($tool));

    Event::assertDispatched(LlmToolCalled::class, function (LlmToolCalled $e) {
        return $e->toolName === 'search_web'
            && $e->result === 'result: Laravel'
            && $e->arguments === ['query' => 'Laravel']
            && $e->traceId === 'trace-tool';
    });
});

it('onEvent tool-called does nothing when tool-calling was not fired first', function () {
    Event::fake();

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $tool = makeTool('unknown_tool', 'result');
    $observer->onEvent('tool-called', new stdClass, new ToolCalled($tool));

    Event::assertNotDispatched(LlmToolCalled::class);
});

it('onEvent tool-called handles getResult exception gracefully', function () {
    Event::fake();

    $context = new TraceContext;
    $context->openTrace('trace-err');
    $observer = new GlintNeuronAiObserver($context);

    $source = makeSourceNode();
    $observer->onEvent('inference-start', $source, new InferenceStart(new Message('Trigger tool')));

    $throwingTool = new class implements ToolInterface
    {
        public function getName(): string
        {
            return 'failing_tool';
        }

        public function getDescription(): ?string
        {
            return null;
        }

        public function getInputs(): array
        {
            return [];
        }

        public function getResult(): string
        {
            throw new RuntimeException('result not available');
        }
    };

    $observer->onEvent('tool-calling', new stdClass, new ToolCalling($throwingTool));
    $observer->onEvent('tool-called', new stdClass, new ToolCalled($throwingTool));

    Event::assertDispatched(LlmToolCalled::class, function (LlmToolCalled $e) {
        return $e->toolName === 'failing_tool' && $e->result === null;
    });
});

it('onEvent ignores unknown event types without throwing', function () {
    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    expect(fn () => $observer->onEvent('some-unknown-event', new stdClass))->not->toThrow(Throwable::class);
});

it('make() resolves from the container', function () {
    $observer = GlintNeuronAiObserver::make();

    expect($observer)->toBeInstanceOf(GlintNeuronAiObserver::class);
});

it('onToolCalling ignores non-ToolCalling data without throwing', function () {
    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    expect(fn () => $observer->onEvent('tool-calling', new stdClass, 'not-a-tool-calling-object'))
        ->not->toThrow(Throwable::class);
});

it('onToolCalled ignores non-ToolCalled data without throwing', function () {
    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    expect(fn () => $observer->onEvent('tool-called', new stdClass, 'not-a-tool-called-object'))
        ->not->toThrow(Throwable::class);
});

it('onError falls back to RuntimeException when data is not AgentError', function () {
    Event::fake();

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $source = makeSourceNode();
    $observer->onEvent('inference-start', $source, new InferenceStart(new Message('Hello')));

    $observer->onEvent('error', $source, 'not-an-agent-error');

    Event::assertDispatched(LlmCallFailed::class, function (LlmCallFailed $e) {
        return str_contains($e->errorMessage, 'NeuronAI agent error');
    });
});

it('resolveProviderAndModel falls back to unknown when provider is not an object', function () {
    Event::fake();

    $source = new class
    {
        protected string $provider = 'not-an-object';
    };

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $observer->onEvent('inference-start', $source, new InferenceStart(new Message('Hello')));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->provider === 'unknown' && $e->model === 'unknown';
    });
});

it('resolveProviderAndModel falls back to unknown when provider has no model property', function () {
    Event::fake();

    $providerWithNoModel = new class {};
    $source = new class($providerWithNoModel)
    {
        public function __construct(protected object $provider) {}
    };

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $observer->onEvent('inference-start', $source, new InferenceStart(new Message('Hello')));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->model === 'unknown';
    });
});

it('resolveProviderAndModel extracts provider name and model via reflection when both exist', function () {
    Event::fake();

    $providerWithModel = new class
    {
        protected string $model = 'gpt-4o';
    };
    $source = new class($providerWithModel)
    {
        public function __construct(protected object $provider) {}
    };

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $observer->onEvent('inference-start', $source, new InferenceStart(new Message('Hello')));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->model === 'gpt-4o';
    });
});

it('resolveProviderAndModel falls back to unknown when source has no provider property', function () {
    Event::fake();

    $source = new stdClass;

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $observer->onEvent('inference-start', $source, new InferenceStart(new Message('Hello')));

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->provider === 'unknown' && $e->model === 'unknown';
    });
});

it('onEvent inference-stop falls back to zero tokens when response has no usage', function () {
    Event::fake();

    $context = new TraceContext;
    $observer = new GlintNeuronAiObserver($context);

    $source = makeSourceNode();
    $observer->onEvent('inference-start', $source, new InferenceStart(new Message('Hello')));

    $responseWithNoUsage = new Message('World');
    $observer->onEvent('inference-stop', $source, new InferenceStop(false, $responseWithNoUsage));

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->promptTokens === 0 && $e->completionTokens === 0;
    });
});
