# Manual Tracing

Use the `Glint` facade to instrument code that auto-instrumentation cannot reach, or to add richer context to automatically captured calls.

## Traces, Spans, and Generations

| Concept | What it represents |
|---------|--------------------|
| **Trace** | A top-level operation (e.g. a user request, a background job) |
| **Span** | A unit of work within a trace (e.g. a DB lookup, an API call) |
| **Generation** | A single LLM call with its tokens, cost, and completion |

## Callback style (recommended)

Wrapping your LLM call in a callback lets Glint manage the lifecycle automatically — the span is closed and status is set correctly even if the callback throws.

```php
use Cybernerdie\Glint\Facades\Glint;

$trace = Glint::trace('chat.pipeline', ['user_id' => auth()->id()]);

$result = $trace->generation(
    name: 'summarise',
    callback: function ($gen) use ($prompt) {
        $response = $this->openai->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $gen->finish(
            completion: $response->choices[0]->message->content,
            promptTokens: $response->usage->promptTokens,
            completionTokens: $response->usage->completionTokens,
        );

        return $response;
    },
    provider: 'openai',
    model: 'gpt-4o',
);

$trace->end();
```

## Open/close style (for streaming or async flows)

For cases where the response arrives in chunks or across multiple callbacks, manage the lifecycle explicitly:

```php
$trace = Glint::trace('document.process');
$trace->tag('doc_id', (string) $document->id);

// Span wrapping a non-LLM step
$span = Glint::span('fetch-context');
// ... fetch step ...
$span->end();

// Generation inherits the active trace automatically
$gen = Glint::generation('summarise', 'openai', 'gpt-4o');
try {
    $response = $this->callLlm($document->content);
    $gen->finish($response->text, $response->promptTokens, $response->completionTokens);
} catch (\Throwable $e) {
    $gen->fail($e);
    throw $e;
}

$trace->end();
```

## Tagging a trace

Tags are stored in the trace's metadata and are visible in the dashboard. Use them to add business context:

```php
$trace->tag('user_id', (string) $user->id);
$trace->tag('plan', $user->plan);

// Or set multiple tags at once
$trace->tags([
    'user_id' => (string) $user->id,
    'plan'    => $user->plan,
]);
```

## Auto-instrumentation and manual tracing together

When `GlintMiddleware` is active (or you've called `Glint::trace()` manually), any auto-instrumented LLM call that happens during that context is automatically attached to the open trace. You don't need to pass trace IDs around.

```php
// GlintMiddleware opens a trace for this request automatically.
// The auto-instrumented HTTP call below is grouped under it — no extra code needed.
$response = Http::post('https://api.openai.com/v1/chat/completions', [...]);
```

## Null safety

When Glint is disabled (`GLINT_ENABLED=false`) or the request is not sampled, `Glint::trace()`, `Glint::span()`, and `Glint::generation()` return null objects (`NullTrace`, `NullSpan`, `NullGeneration`). These implement the same interfaces and are completely safe to call — they just do nothing.

```php
// This is always safe regardless of whether Glint is enabled
$trace = Glint::trace('pipeline');
$trace->tag('key', 'value'); // no-op if disabled
$trace->end();               // no-op if disabled
```
