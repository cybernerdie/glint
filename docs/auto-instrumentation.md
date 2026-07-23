# Auto-Instrumentation

Auto-instrumentation means Glint captures LLM calls **without any changes to your application code**. Install the package, set `GLINT_ENABLED=true`, choose a driver, and every matching LLM call is recorded automatically.

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
| `http` | *(built-in)* | Universal fallback — works with any SDK that uses Laravel's HTTP client |
| `prism` | `echolabsdev/prism` | You're using the Prism SDK |
| `laravel-ai` | `illuminate/ai` (Laravel 12+) | You're using Laravel's built-in AI layer |
| `neuron-ai` | `useiconic/neuron-ai` | You're using the NeuronAI agent framework |

Set one or more drivers via `GLINT_DRIVERS` (comma-separated):

```env
GLINT_DRIVERS=http
GLINT_DRIVERS=prism
GLINT_DRIVERS=http,prism
```

See [Drivers](drivers.md) for the full reference on each driver and how to add a custom one.

## HTTP request context (optional)

Register `GlintMiddleware` globally if you want LLM calls grouped by the HTTP request that triggered them:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Cybernerdie\Glint\Middleware\GlintMiddleware::class);
})
```

Without it, generations are still recorded — they just won't have a parent trace showing which route or user triggered them.

## Background jobs and console commands

LLM calls made inside queued jobs and Artisan commands are always captured. Without `GlintMiddleware` there is no HTTP request context, so Glint defaults to recording everything.

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
