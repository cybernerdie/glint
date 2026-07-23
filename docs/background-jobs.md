# Background Jobs & Queue Compatibility

## Always recorded

LLM calls made inside queued jobs, scheduled commands, and Artisan commands are **always recorded**, regardless of `sampling_rate`.

`sampling_rate` only applies to HTTP requests processed by `GlintMiddleware`. Outside of an HTTP request context there is no sampling decision to inherit, so Glint defaults to recording everything.

This is intentional — background jobs are typically where the most important LLM work happens.

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

Glint uses `queue` mode by default. When an LLM call is recorded inside a job, Glint dispatches another small `RecordLlmCallJob`. If you want to avoid nested queue dispatches in jobs, set `GLINT_MODE=sync` for the queue worker environment via a dedicated `.env` or environment variable override.
