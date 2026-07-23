# Background Jobs & Queue Compatibility

## Recording in jobs and commands

LLM calls made inside queued jobs, scheduled commands, and Artisan commands are recorded when an active driver sees them.

Outside of an HTTP request context there is no `GlintMiddleware` trace. If you do not open a trace manually, Glint creates an automatic trace for the generation.

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

When `GLINT_MODE=queue` (the default), every LLM event dispatches a `RecordLlmCallJob` to write the record asynchronously. Inside a queued job this means your worker can dispatch another job for Glint recording. If you prefer inline writes inside workers, set the mode for those worker processes:

```env
# .env (or an environment variable set on the worker process)
GLINT_MODE=sync
```

Your web processes can keep `queue` mode while workers use `sync`, as long as you set different environment values for those process groups.
