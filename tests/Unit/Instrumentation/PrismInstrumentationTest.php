<?php

declare(strict_types=1);

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Instrumentation\Prism\TracingPrismManager;
use Cybernerdie\Glint\Instrumentation\Prism\TracingProvider;
use Cybernerdie\Glint\Instrumentation\PrismInstrumentation;
use Illuminate\Support\Facades\Event;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\Anthropic;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Text\Request;
use Prism\Prism\Text\Response;
use Tests\Stubs\FinishReasonEnum;

function prismTextRequest(
    string $model = 'test-model',
    array $messages = [],
    int|float|null $temperature = null,
    ?int $maxTokens = null,
    int|float|null $topP = null,
): Request {
    return new Request($model, $messages, $temperature, $maxTokens, $topP);
}

it('isAvailable returns true when Prism stub class exists', function () {
    $instrumentation = new PrismInstrumentation($this->app);

    expect($instrumentation->isAvailable())->toBeTrue();
});

it('isAvailable returns false for a class that does not exist', function () {
    expect(class_exists('Prism\\NonExistent\\ClassThatDoesNotExist'))->toBeFalse();
});

it('register() wraps PrismManager with TracingPrismManager in the container', function () {
    $this->app->singleton(PrismManager::class, fn () => new PrismManager($this->app));

    $instrumentation = new PrismInstrumentation($this->app);
    $instrumentation->register();

    $resolved = $this->app->make(PrismManager::class);

    expect($resolved)->toBeInstanceOf(TracingPrismManager::class);
});

it('TracingProvider fires LlmCallStarted before delegating text() call', function () {
    Event::fake();

    $inner = new Anthropic;

    $context = new TraceContext;
    $context->openTrace('trace-123');

    $provider = new TracingProvider($inner, $context);

    $request = prismTextRequest(
        model: 'claude-3-5-sonnet-20241022',
        messages: [['role' => 'user', 'content' => 'Hi']],
        temperature: 0.7,
        maxTokens: 100,
        topP: 0.9,
    );

    config()->set('glint.recording.store_bodies', true);

    $provider->text($request);

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->provider === 'anthropic'
            && $e->model === 'claude-3-5-sonnet-20241022'
            && $e->topP === 0.9
            && $e->traceId === 'trace-123';
    });
});

it('TracingProvider fires LlmCallFinished after successful text() call', function () {
    Event::fake();

    $inner = new class extends Provider
    {
        public function text(mixed $request): Response
        {
            return new Response(
                text: 'Response text',
                usage: (object) ['promptTokens' => 10, 'completionTokens' => 5],
                finishReason: 'stop',
            );
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    config()->set('glint.recording.store_bodies', true);

    $provider->text(prismTextRequest(model: 'claude-3'));

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->promptTokens === 10
            && $e->completionTokens === 5
            && $e->finishReason === 'stop'
            && $e->completion === 'Response text';
    });
});

it('TracingProvider fires LlmCallFailed and re-throws exception on error', function () {
    Event::fake();

    $inner = new class extends Provider
    {
        public function text(mixed $request): never
        {
            throw new RuntimeException('Provider error');
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    expect(fn () => $provider->text(prismTextRequest(model: 'gpt-4')))
        ->toThrow(RuntimeException::class, 'Provider error');

    Event::assertDispatched(LlmCallFailed::class, function (LlmCallFailed $e) {
        return $e->errorMessage === 'Provider error';
    });
});

it('TracingProvider fires both LlmCallStarted and LlmCallFinished in correct order', function () {
    $fired = [];

    Event::listen(LlmCallStarted::class, function () use (&$fired) {
        $fired[] = 'started';
    });

    Event::listen(LlmCallFinished::class, function () use (&$fired) {
        $fired[] = 'finished';
    });

    $inner = new class extends Provider
    {
        public function text(mixed $request): Response
        {
            return new Response;
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    $provider->text(prismTextRequest());

    expect($fired)->toBe(['started', 'finished']);
});

it('TracingProvider passes through non-text calls via __call', function () {
    $inner = new class extends Provider
    {
        public function embeddings(string $text): string
        {
            return 'embedding-result';
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    expect($provider->embeddings('hello'))->toBe('embedding-result');
});

it('TracingProvider throws BadMethodCallException for __call to unknown method', function () {
    $inner = new class extends Provider {};

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    expect(fn () => $provider->nonExistentMethod())->toThrow(BadMethodCallException::class);
});

it('TracingProvider defaults finishReason to stop when finishReason is not a string or BackedEnum', function () {
    Event::fake();

    $inner = new class extends Provider
    {
        public function text(mixed $request): Response
        {
            return new Response(
                usage: (object) ['promptTokens' => 1, 'completionTokens' => 1],
                finishReason: 99,
            );
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    $provider->text(prismTextRequest());

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->finishReason === 'stop';
    });
});

it('TracingProvider uses BackedEnum value for finishReason', function () {
    Event::fake();

    $inner = new class extends Provider
    {
        public function text(mixed $request): Response
        {
            return new Response(
                usage: (object) ['promptTokens' => 1, 'completionTokens' => 1],
                finishReason: FinishReasonEnum::Stop,
            );
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    config()->set('glint.recording.store_bodies', false);

    $provider->text(prismTextRequest(model: 'test-model'));

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->finishReason === 'stop';
    });
});

it('resolves provider name using hardcoded map for Provider-suffixed class names', function () {
    Event::fake();

    $inner = new Anthropic;

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    $provider->text(prismTextRequest(model: 'claude-3-5-sonnet-20241022'));

    Event::assertDispatched(LlmCallStarted::class, fn (LlmCallStarted $e) => $e->provider === 'anthropic');
});

it('TracingPrismManager is a PrismManager instance', function () {
    $inner = new PrismManager($this->app);
    $manager = new TracingPrismManager($inner, $this->app);

    expect($manager)->toBeInstanceOf(PrismManager::class);
});
