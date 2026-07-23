# Auto-Instrumentation

Auto-instrumentation records supported LLM calls without changing the call site. Install the package, set `GLINT_ENABLED=true`, choose a driver, and matching calls are recorded.

## How it works

Each driver hooks into framework-level events or wraps the SDK's entry point. When an LLM call is detected, the driver fires these internal Glint events:

| Event | When |
|-------|------|
| `LlmCallStarted` | Request is about to be sent |
| `LlmCallFinished` | Successful response received |
| `LlmCallFailed` | HTTP error or connection failure |
| `LlmToolCalled` | Tool/function call returned a result |

`GlintRecorder` listens to these events and writes to the database (or `RecordLlmCallJob` does it asynchronously in `queue` mode).

## Available drivers

| Driver | Package | When to use |
|--------|---------|-------------|
| `http` | *(built-in)* | LLM requests sent through Laravel's HTTP client |
| `prism` | `echolabsdev/prism` | You're using the Prism SDK |
| `laravel-ai` | `laravel/ai` | You're using Laravel's AI layer |
| `neuron-ai` | `useiconic/neuron-ai` | You're using the NeuronAI agent framework |

Set one or more drivers via `GLINT_DRIVERS` (comma-separated):

```env
GLINT_DRIVERS=http
GLINT_DRIVERS=prism
GLINT_DRIVERS=http,prism
```

See [Drivers](drivers.md) for the full reference on each driver and how to add a custom one.

Auto-instrumentation is driver-specific. If your SDK uses its own Guzzle, PSR-18, cURL, or provider-managed transport, Glint may not see the call automatically. Use [manual tracing](manual-tracing.md) for unsupported clients.

## HTTP request context (optional)

Register `GlintMiddleware` globally if you want LLM calls grouped by the HTTP request that triggered them:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Cybernerdie\Glint\Middleware\GlintMiddleware::class);
})
```

Without it, generations are still recorded, but they do not have a parent trace showing which route or user triggered them.

## Background jobs and console commands

LLM calls made inside queued jobs and Artisan commands are recorded when an active driver sees them. Without `GlintMiddleware` there is no HTTP request context, so Glint creates an automatic trace unless you open one manually.

See [Background Jobs](background-jobs.md) for details on adding trace context inside jobs.

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
