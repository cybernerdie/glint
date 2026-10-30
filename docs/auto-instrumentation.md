# Auto-Instrumentation

Auto-instrumentation records supported LLM calls without changing the call site. Install the package, set `GLINT_ENABLED=true`, choose a driver, and matching calls are recorded.

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

### Laravel AI failures

Laravel AI does not emit a terminal event when an agent prompt or stream throws. Add Glint's prompt middleware to each agent whose failures you want recorded:

```php
use Cybernerdie\Glint\Instrumentation\LaravelAi\GlintPromptMiddleware;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;

final class MyAgent implements Agent, HasMiddleware
{
    use Promptable;

    public function middleware(): array
    {
        return [GlintPromptMiddleware::class];
    }
}
```

If the agent already implements `HasMiddleware`, append `GlintPromptMiddleware::class` to its existing `middleware()` array. Put it last so it receives the prompt that Laravel AI sends to the provider. The middleware records the original exception on the matching generation, then rethrows it. It also wraps stream iteration so thrown errors and terminal stream error events close the matching generation as failed. A stream that is never consumed has not made an LLM call and has no generation to close.
## HTTP request context (optional)

Register `GlintMiddleware` globally if you want LLM calls grouped by the HTTP request that triggered them:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Cybernerdie\Glint\Middleware\GlintMiddleware::class);
})
```

Glint creates a request trace only when a generation or span is recorded during that request. Other requests do not create trace rows. Without the middleware, generations are still recorded, but they do not have a parent trace showing which route or user triggered them.

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
