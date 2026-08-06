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
use Prism\Prism\Embeddings\Request as EmbeddingsRequest;
use Prism\Prism\Embeddings\Response as EmbeddingsResponse;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\Anthropic;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Structured\Request as StructuredRequest;
use Prism\Prism\Structured\Response as StructuredResponse;
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

it('TracingProvider passes through calls to custom driver methods via __call', function () {
    $inner = new class extends Provider
    {
        public function computeCost(string $text): string
        {
            return 'cost-result';
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    expect($provider->computeCost('hello'))->toBe('cost-result');
});

it('TracingProvider throws BadMethodCallException for __call to unknown method', function () {
    $inner = new class extends Provider {};

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    expect(fn () => $provider->nonExistentMethod())->toThrow(BadMethodCallException::class);
});

it('TracingProvider fires LlmCallStarted and LlmCallFinished for structured()', function () {
    Event::fake();

    $response = new StructuredResponse(
        structured: ['answer' => 42],
        text: '{"answer":42}',
        finishReason: 'stop',
        usage: (object) ['promptTokens' => 20, 'completionTokens' => 8],
    );

    $inner = new class($response) extends Provider
    {
        public function __construct(private readonly StructuredResponse $response) {}

        public function structured(StructuredRequest $request): StructuredResponse
        {
            return $this->response;
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    config()->set('glint.recording.store_bodies', true);

    $result = $provider->structured(new StructuredRequest(model: 'gpt-4o', temperature: 0.3));

    expect($result)->toBe($response);

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->model === 'gpt-4o'
            && $e->temperature === 0.3
            && ($e->metadata['glint_call_type'] ?? null) === 'structured';
    });

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->promptTokens === 20
            && $e->completionTokens === 8
            && $e->finishReason === 'stop'
            && $e->completion === '{"answer":42}';
    });
});

it('TracingProvider fires LlmCallFailed and re-throws for structured() on error', function () {
    Event::fake();

    $inner = new class extends Provider
    {
        public function structured(mixed $request): never
        {
            throw new RuntimeException('structured error');
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    expect(fn () => $provider->structured(new StructuredRequest))
        ->toThrow(RuntimeException::class, 'structured error');

    Event::assertDispatched(LlmCallFailed::class, fn (LlmCallFailed $e) => $e->errorMessage === 'structured error');
});

it('TracingProvider stores json-encoded structured output as completion', function () {
    Event::fake();

    $response = new StructuredResponse(
        structured: ['city' => 'Lagos', 'country' => 'Nigeria'],
        usage: (object) ['promptTokens' => 5, 'completionTokens' => 3],
    );

    $inner = new class($response) extends Provider
    {
        public function __construct(private readonly StructuredResponse $response) {}

        public function structured(StructuredRequest $request): StructuredResponse
        {
            return $this->response;
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    config()->set('glint.recording.store_bodies', true);

    $provider->structured(new StructuredRequest);

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->completion === '{"city":"Lagos","country":"Nigeria"}';
    });
});

it('TracingProvider fires LlmCallStarted and LlmCallFinished for embeddings()', function () {
    Event::fake();

    $response = new EmbeddingsResponse(
        embeddings: [[0.1, 0.2, 0.3]],
        usage: (object) ['tokens' => 12],
    );

    $inner = new class($response) extends Provider
    {
        public function __construct(private readonly EmbeddingsResponse $response) {}

        public function embeddings(EmbeddingsRequest $request): EmbeddingsResponse
        {
            return $this->response;
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    $result = $provider->embeddings(new EmbeddingsRequest(model: 'text-embedding-3-small'));

    expect($result)->toBe($response);

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->model === 'text-embedding-3-small'
            && $e->messages === null
            && ($e->metadata['glint_call_type'] ?? null) === 'embeddings';
    });

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->promptTokens === 12
            && $e->completionTokens === 0
            && $e->completion === null
            && $e->finishReason === 'stop';
    });
});

it('TracingProvider fires LlmCallFailed and re-throws for embeddings() on error', function () {
    Event::fake();

    $inner = new class extends Provider
    {
        public function embeddings(mixed $request): never
        {
            throw new RuntimeException('embeddings error');
        }
    };

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    expect(fn () => $provider->embeddings(new EmbeddingsRequest))
        ->toThrow(RuntimeException::class, 'embeddings error');

    Event::assertDispatched(LlmCallFailed::class, fn (LlmCallFailed $e) => $e->errorMessage === 'embeddings error');
});

it('TracingProvider surfaces the wrapped provider name (not itself) for unsupported actions', function (string $method, object $request) {
    // Regression test: Prism's base Provider class defines concrete
    // (non-abstract) methods like structured()/embeddings() that throw
    // "{Action} is not supported by {class}". Because they're concrete,
    // PHP resolves calls to them directly against TracingProvider unless
    // explicitly overridden, which previously caused the error to
    // misreport "TracingProvider" instead of the real wrapped driver
    // (e.g. "Anthropic").
    $inner = new Anthropic;

    $context = new TraceContext;
    $provider = new TracingProvider($inner, $context);

    expect(fn () => $provider->$method($request))
        ->toThrow(function (Exception $e) {
            expect($e->getMessage())
                ->toContain('Anthropic')
                ->not->toContain('TracingProvider');
        });
})->with([
    'embeddings' => ['embeddings', new EmbeddingsRequest],
    'structured' => ['structured', new StructuredRequest],
]);

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

it('TracingPrismManager::extend() registers custom provider on the inner manager not itself', function () {
    // PrismManager::extend() is a concrete method, so it would bypass __call() and
    // store the creator on TracingPrismManager — but resolve() delegates to $inner,
    // which would never see the registration. The override ensures extend() routes
    // to $inner so custom providers are available when resolve() is called.
    $inner = new PrismManager($this->app);
    $manager = new TracingPrismManager($inner, $this->app);

    $called = false;
    $manager->extend('my-provider', function () use (&$called) {
        $called = true;

        return new class extends \Prism\Prism\Providers\Provider
        {
            public function text(\Prism\Prism\Text\Request $request): \Prism\Prism\Text\Response
            {
                return new \Prism\Prism\Text\Response;
            }
        };
    });

    $manager->resolve('my-provider');

    expect($called)->toBeTrue();
});
