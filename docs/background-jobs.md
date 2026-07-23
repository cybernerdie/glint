# Background Jobs & Queue Compatibility

## Always recorded

LLM calls made inside queued jobs, scheduled commands, and Artisan commands are **always recorded**.

Outside of an HTTP request context there is no `GlintMiddleware` to decide otherwise, so Glint defaults to recording everything. This is intentional — background jobs are typically where the most important LLM work happens.

## Suppressing recording in jobs

If you need to opt out of recording inside a specific job, register a filter:

```php
// AppServiceProvider::boot()
use Cybernerdie\Glint\Facades\Glint;

Glint::filter(fn ($entry) => ! app()->runningInConsole());
```

Or filter by a specific job class via metadata:

```php
Glint::filter(function ($entry) {
    return ($entry->metadata['job'] ?? null) !== MyHighVolumeJob::class;
});
```

## Adding trace context in jobs

Without `GlintMiddleware`, LLM calls in jobs have no parent trace. You can add one manually:

```php
class GenerateSummaryJob implements ShouldQueue
{
    public function handle(): void
    {
        $trace = Glint::trace('generate-summary', [
            'document_id' => $this->documentId,
            'job' => static::class,
        ]);

        try {
            // LLM call here — auto-instrumentation attaches it to $trace
            $response = $this->callLlm();
        } finally {
            $trace->end();
        }
    }
}
```

## Queue mode inside a job

When `GLINT_MODE=queue` (the default), every LLM call causes Glint to dispatch a small `RecordLlmCallJob` to write the record asynchronously. Inside a queued job this means your worker picks up one job, which dispatches another job. That is perfectly fine in most setups, but if you'd rather have Glint write the record inline — without the extra job hop — you can override the mode for your worker processes only:

```env
# .env (or an environment variable set on the worker process)
GLINT_MODE=sync
```

Your web processes keep the non-blocking `queue` mode; the workers write synchronously and no additional jobs are dispatched.
