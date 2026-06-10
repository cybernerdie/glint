# Auto-Instrumentation

Auto-instrumentation means Glint captures LLM calls **without any changes to your application code**. Install the package, set `GLINT_ENABLED=true`, choose a driver, and every matching LLM call is recorded automatically.

## How it works

Each driver registers listeners on framework-level hooks:

- The `http` driver listens to `RequestSending` and `ResponseReceived` events from Laravel's HTTP client
- The `prism` driver wraps Prism's provider manager with a tracing decorator

When an outgoing request to a known LLM host is detected, the driver fires these internal events:

| Event | When |
|-------|------|
| `LlmCallStarted` | Request is about to be sent |
| `LlmCallFinished` | Successful response received |
| `LlmCallFailed` | HTTP error or connection failure |
| `LlmToolCalled` | Tool/function call returned a result |

`GlintRecorder` listens to these events and writes to the database (or `RecordLlmCallJob` does it asynchronously in `queue` mode).

## HTTP request context (optional)

Register `GlintMiddleware` globally if you want LLM calls grouped by the HTTP request that triggered them:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Cybernerdie\Glint\Middleware\GlintMiddleware::class);
})
```

Without it, generations are still recorded — they just won't have a parent trace showing which route/user triggered them.

## Background jobs and console commands

LLM calls made inside queued jobs and Artisan commands are always captured, regardless of `sampling_rate`. The middleware sampling decision only applies to HTTP requests.

See [Background Jobs](background-jobs.md) for details.

## Filtering

You can register a callback to decide whether a specific call should be recorded:

```php
// AppServiceProvider::boot()
use Cybernerdie\Glint\Facades\Glint;
use Cybernerdie\Glint\Filtering\FilterEntry;

Glint::filter(function (FilterEntry $entry): bool {
    // Skip test/sandbox model calls
    return ! str_contains($entry->model, 'test');
});
```

Return `false` to skip recording. Multiple filters can be registered; all must return `true` for the call to be recorded.
