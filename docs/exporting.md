# Exporting and external observability

Glint currently stores observability data inside the host Laravel application and provides extension points for teams that need to forward selected data elsewhere.

It does not currently ship a first-party OpenTelemetry bridge, Prometheus endpoint, Datadog exporter, Honeycomb exporter, or warehouse connector.

## Current extension points

### Laravel events

Glint emits Laravel events during the LLM call lifecycle:

- `Cybernerdie\Glint\Events\LlmCallStarted`
- `Cybernerdie\Glint\Events\LlmCallFinished`
- `Cybernerdie\Glint\Events\LlmCallFailed`
- `Cybernerdie\Glint\Events\LlmToolCalled`

It also emits operational events:

- `Cybernerdie\Glint\Events\GlintAlertTriggered`
- `Cybernerdie\Glint\Events\GlintDataPruned`
- `Cybernerdie\Glint\Events\GlintDataCleared`

Use these events when your application needs to forward a subset of data to logs, queues, alerting tools, or internal pipelines.

```php
use Cybernerdie\Glint\Events\LlmCallFinished;
use Illuminate\Support\Facades\Event;

Event::listen(LlmCallFinished::class, function (LlmCallFinished $event): void {
    // Dispatch an app-owned job, write to logs, or forward a small summary.
});
```

Lifecycle events are useful for custom hooks, but they are not a complete telemetry export contract. If your exporter needs the final persisted row, provider/model dimensions, traces, spans, or aggregate data, read from the `glint_*` tables after Glint records the data.

### Alert webhooks

Glint supports alert destinations such as mail, Slack, generic webhooks, and logs.

These destinations are for alert notifications only. They are not intended to replace a full telemetry export pipeline.

### Direct database reads

For internal BI jobs, warehouse syncs, or app-owned export pipelines, read from Glint's tables in a read-only scheduled job:

- `glint_generations`
- `glint_traces`
- `glint_spans`
- `glint_aggregates`
- `glint_alert_rules`
- `glint_alert_events`

Keep the export job owned by the host application so it can control batching, retries, credentials, network access, and destination-specific schemas.

### Correlation metadata

For manual tracing, add correlation metadata through tags:

```php
Glint::trace('checkout', function ($trace): void {
    $trace->tags([
        'request_id' => request()->headers->get('X-Request-Id'),
        'deployment' => config('app.version'),
        'environment' => app()->environment(),
    ]);
});
```

For queued jobs, include job IDs, tenant IDs, team IDs, or deployment metadata when creating traces, spans, or generations.

## Deferred export targets

The following are intentionally deferred until there is a concrete target format and consumer:

- OpenTelemetry span export.
- Prometheus-compatible metrics endpoint.
- Datadog, Honeycomb, Grafana, or log exporter packages.
- First-party warehouse connectors.

This keeps the package's public surface small while still allowing applications to build their own export path from events and stored data.
