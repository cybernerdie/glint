<?php

declare(strict_types=1);

use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Events\LlmCallFailed;
use Cybernerdie\Glint\Events\LlmCallFinished;
use Cybernerdie\Glint\Events\LlmCallStarted;
use Cybernerdie\Glint\Instrumentation\HttpClientInstrumentation;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Psr\Http\Message\StreamInterface;

function makeHttpRequest(string $url, string $body = ''): Request
{
    $psrRequest = new GuzzleHttp\Psr7\Request(
        'POST',
        $url,
        ['Content-Type' => 'application/json'],
        $body,
    );

    return new Request($psrRequest);
}

it('isAvailable returns true', function () {
    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    expect($instrumentation->isAvailable())->toBeTrue();
});

it('onRequestSending does nothing for non-LLM hosts', function () {
    Event::fake();

    config()->set('glint.llm_hosts', [
        'api.openai.com' => 'openai',
    ]);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $request = makeHttpRequest('https://example.com/api/endpoint', json_encode(['model' => 'gpt-4']));
    $event = new RequestSending($request);

    $instrumentation->onRequestSending($event);

    Event::assertNotDispatched(LlmCallStarted::class);
});

it('onRequestSending fires LlmCallStarted for api.openai.com', function () {
    Event::fake();

    config()->set('glint.llm_hosts', [
        'api.openai.com' => 'openai',
    ]);
    config()->set('glint.recording.store_bodies', true);

    $context = new TraceContext;
    $context->openTrace('trace-abc', true);
    $instrumentation = new HttpClientInstrumentation($context);

    $body = json_encode([
        'model' => 'gpt-4o',
        'messages' => [['role' => 'user', 'content' => 'Hello']],
        'temperature' => 0.5,
        'max_tokens' => 200,
    ]);

    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', $body);
    $event = new RequestSending($request);

    $instrumentation->onRequestSending($event);

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->provider === 'openai'
            && $e->model === 'gpt-4o'
            && $e->traceId === 'trace-abc'
            && $e->temperature === 0.5
            && $e->maxTokens === 200
            && $e->isStreaming === false;
    });
});

it('onRequestSending sets isStreaming true when stream flag present', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);
    config()->set('glint.recording.store_bodies', false);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $body = json_encode([
        'model' => 'gpt-4o',
        'stream' => true,
    ]);

    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', $body);
    $event = new RequestSending($request);

    $instrumentation->onRequestSending($event);

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->isStreaming === true;
    });
});

it('onRequestSending does not store messages when store_bodies is false', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);
    config()->set('glint.recording.store_bodies', false);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $body = json_encode([
        'model' => 'gpt-4o',
        'messages' => [['role' => 'user', 'content' => 'Secret']],
    ]);

    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', $body);
    $event = new RequestSending($request);

    $instrumentation->onRequestSending($event);

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->messages === null;
    });
});

it('onResponseReceived fires LlmCallFinished for a tracked request', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);
    config()->set('glint.recording.store_bodies', true);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $requestBody = json_encode(['model' => 'gpt-4o', 'messages' => [['role' => 'user', 'content' => 'Hi']]]);
    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', $requestBody);

    $instrumentation->onRequestSending(new RequestSending($request));

    $responseBody = json_encode([
        'choices' => [['message' => ['content' => 'Hello!'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ]);

    $psrResponse = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
    $response = new Illuminate\Http\Client\Response($psrResponse);

    $instrumentation->onResponseReceived(new ResponseReceived($request, $response));

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->promptTokens === 10 && $e->completionTokens === 5 && $e->completion === 'Hello!';
    });
});

it('onResponseReceived does nothing for untracked requests', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $untrackedRequest = makeHttpRequest('https://api.openai.com/v1/chat/completions', '{}');
    $psrResponse = new Response(200, [], '{}');
    $response = new Illuminate\Http\Client\Response($psrResponse);

    $instrumentation->onResponseReceived(new ResponseReceived($untrackedRequest, $response));

    Event::assertNotDispatched(LlmCallFinished::class);
});

it('onConnectionFailed fires LlmCallFailed for a tracked request', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', json_encode(['model' => 'gpt-4o']));

    $instrumentation->onRequestSending(new RequestSending($request));

    $connException = new ConnectionException('Connection refused');
    $instrumentation->onConnectionFailed(new ConnectionFailed($request, $connException));

    Event::assertDispatched(LlmCallFailed::class, function (LlmCallFailed $e) {
        return str_contains($e->errorMessage, 'connection failed');
    });
});

it('onConnectionFailed does nothing for untracked requests', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $untrackedRequest = makeHttpRequest('https://api.openai.com/v1/chat/completions', '{}');
    $connException = new ConnectionException('Connection refused');

    $instrumentation->onConnectionFailed(new ConnectionFailed($untrackedRequest, $connException));

    Event::assertNotDispatched(LlmCallFailed::class);
});

it('onResponseReceived with store_bodies false passes null completion', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);
    config()->set('glint.recording.store_bodies', false);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', json_encode(['model' => 'gpt-4o']));

    $instrumentation->onRequestSending(new RequestSending($request));

    $responseBody = json_encode([
        'choices' => [['message' => ['content' => 'Secret'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
    ]);
    $psrResponse = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
    $response = new Illuminate\Http\Client\Response($psrResponse);

    $instrumentation->onResponseReceived(new ResponseReceived($request, $response));

    Event::assertDispatched(LlmCallFinished::class, function (LlmCallFinished $e) {
        return $e->completion === null;
    });
});

it('register attaches event listeners', function () {
    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    expect(fn () => $instrumentation->register())->not->toThrow(Throwable::class);
});

it('onRequestSending catches body() exceptions gracefully', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $throwingStream = new class implements StreamInterface
    {
        public function __toString(): string
        {
            throw new RuntimeException('stream error');
        }

        public function close(): void {}

        public function detach()
        {
            return null;
        }

        public function getSize(): ?int
        {
            return null;
        }

        public function tell(): int
        {
            return 0;
        }

        public function eof(): bool
        {
            return true;
        }

        public function isSeekable(): bool
        {
            return false;
        }

        public function seek(int $offset, int $whence = SEEK_SET): void {}

        public function rewind(): void {}

        public function isWritable(): bool
        {
            return false;
        }

        public function write(string $string): int
        {
            return 0;
        }

        public function isReadable(): bool
        {
            return false;
        }

        public function read(int $length): string
        {
            return '';
        }

        public function getContents(): string
        {
            throw new RuntimeException('stream error');
        }

        public function getMetadata(?string $key = null): mixed
        {
            return null;
        }
    };

    $psrRequest = new GuzzleHttp\Psr7\Request(
        'POST',
        'https://api.openai.com/v1/chat/completions',
        ['Content-Type' => 'application/json'],
        $throwingStream,
    );
    $request = new Request($psrRequest);
    $event = new RequestSending($request);

    $instrumentation->onRequestSending($event);

    Event::assertDispatched(LlmCallStarted::class, function (LlmCallStarted $e) {
        return $e->model === 'unknown';
    });
});

it('cleans up stale pending entry when onRequestSending fires twice for same request object', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);
    config()->set('glint.recording.store_bodies', false);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', json_encode(['model' => 'gpt-4o']));

    $instrumentation->onRequestSending(new RequestSending($request));
    $instrumentation->onRequestSending(new RequestSending($request));

    Event::assertDispatched(LlmCallStarted::class);
});

it('onResponseReceived catches json() exceptions gracefully', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);
    config()->set('glint.recording.store_bodies', false);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', json_encode(['model' => 'gpt-4o']));
    $instrumentation->onRequestSending(new RequestSending($request));

    $throwingStream = new class implements StreamInterface
    {
        public function __toString(): string
        {
            throw new RuntimeException('read error');
        }

        public function close(): void {}

        public function detach()
        {
            return null;
        }

        public function getSize(): ?int
        {
            return null;
        }

        public function tell(): int
        {
            return 0;
        }

        public function eof(): bool
        {
            return false;
        }

        public function isSeekable(): bool
        {
            return false;
        }

        public function seek(int $offset, int $whence = SEEK_SET): void {}

        public function rewind(): void {}

        public function isWritable(): bool
        {
            return false;
        }

        public function write(string $string): int
        {
            return 0;
        }

        public function isReadable(): bool
        {
            return true;
        }

        public function read(int $length): string
        {
            throw new RuntimeException('read error');
        }

        public function getContents(): string
        {
            throw new RuntimeException('read error');
        }

        public function getMetadata(?string $key = null): mixed
        {
            return null;
        }
    };

    $psrResponse = new Response(200, [], $throwingStream);
    $response = new Illuminate\Http\Client\Response($psrResponse);

    $instrumentation->onResponseReceived(new ResponseReceived($request, $response));

    Event::assertDispatched(LlmCallFinished::class);
});

it('onResponseReceived does nothing when pending entry is missing for a known correlation', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', json_encode(['model' => 'gpt-4o']));

    $instrumentation->onRequestSending(new RequestSending($request));

    $ref = new ReflectionClass($instrumentation);

    $pendingProp = $ref->getProperty('pending');
    $pendingProp->setAccessible(true);
    $pendingProp->setValue($instrumentation, []);  // clear pending while hashMap still has entry

    $responseBody = json_encode(['choices' => [['message' => ['content' => 'Hi'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3]]);
    $psrResponse = new Response(200, ['Content-Type' => 'application/json'], $responseBody);
    $response = new Illuminate\Http\Client\Response($psrResponse);

    $instrumentation->onResponseReceived(new ResponseReceived($request, $response));

    Event::assertNotDispatched(LlmCallFinished::class);
});

it('onResponseReceived fires LlmCallFailed when the HTTP response is 4xx or 5xx', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', json_encode(['model' => 'gpt-4o']));
    $instrumentation->onRequestSending(new RequestSending($request));

    $psrResponse = new Response(429, [], json_encode(['error' => ['message' => 'Rate limit exceeded']]));
    $response = new Illuminate\Http\Client\Response($psrResponse);

    $instrumentation->onResponseReceived(new ResponseReceived($request, $response));

    Event::assertDispatched(LlmCallFailed::class, function (LlmCallFailed $e) {
        return str_contains($e->errorMessage, 'HTTP 429');
    });
    Event::assertNotDispatched(LlmCallFinished::class);
});

it('onConnectionFailed does nothing when pending entry is missing for a known correlation', function () {
    Event::fake();

    config()->set('glint.llm_hosts', ['api.openai.com' => 'openai']);

    $context = new TraceContext;
    $instrumentation = new HttpClientInstrumentation($context);

    $request = makeHttpRequest('https://api.openai.com/v1/chat/completions', json_encode(['model' => 'gpt-4o']));

    $instrumentation->onRequestSending(new RequestSending($request));

    $ref = new ReflectionClass($instrumentation);

    $pendingProp = $ref->getProperty('pending');
    $pendingProp->setAccessible(true);
    $pendingProp->setValue($instrumentation, []);  // clear pending while hashMap still has entry

    $connException = new ConnectionException('Connection refused');

    $instrumentation->onConnectionFailed(new ConnectionFailed($request, $connException));

    Event::assertNotDispatched(LlmCallFailed::class);
});
