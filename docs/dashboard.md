# Dashboard

The Glint dashboard is available at `/glint` (configurable via `GLINT_PATH`).

## Pages

**Dashboard** (`/glint`) — Summary stats: total generations, total cost, average latency, error rate, recent traces, and cost by provider.

**Traces** (`/glint/traces`) — All HTTP request traces. Each trace shows the route, user, duration, status, and how many LLM generations happened within it. Only available when `GlintMiddleware` is registered.

**Trace detail** (`/glint/traces/{id}`) — Expanded view of a single trace with all its child spans and generations.

**Generations** (`/glint/generations`) — All LLM calls. Filterable by provider, model, and status. Shows tokens, cost, latency, and finish reason.

**Generation detail** (`/glint/generations/{id}`) — Full detail for a single generation including prompt and completion, unless body storage is disabled with `GLINT_STORE_BODIES=false`.

**Costs** (`/glint/costs`) — Cost breakdown by provider and model over time.

**Users** (`/glint/users`) — Per-user consumption summaries when traces include a `user_id`.

**Latency** (`/glint/analytics/latency`) — p50, p90, p95, and p99 latency by named trace and model.

**Alerts** (`/glint/alerts`) — Create and manage threshold-based alert rules.

## Current scope and limits

The dashboard gives Laravel teams an application-local view of LLM traffic, cost, latency, errors, traces, generations, users, and alerts.

It does not currently include:

- Saved filters.
- CSV or JSON export buttons.
- Per-user, per-team, or per-tenant budget workflows.
- Alert history analytics beyond the stored alert events.
- Unknown pricing warnings inside the UI.
- OpenTelemetry, Prometheus, Datadog, Honeycomb, Grafana, or warehouse export screens.

For external export options, see [Exporting and external observability](exporting.md).

## Access control

The dashboard is protected by the `viewGlint` gate, which is defined in the published `app/Providers/GlintServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewGlint', function ($user) {
        return in_array($user->email, [
            'admin@example.com',
        ]);
    });
}
```

In the **local environment** the gate is bypassed automatically — you can view the dashboard without logging in.

For **production**, add the email addresses (or any other check) of users who should have access.

## Customising the path

```env
GLINT_PATH=telescope-llm
```

The path must only contain alphanumeric characters, hyphens, underscores, and forward slashes. Invalid values fall back to `glint`.

## Customising middleware

By default the dashboard uses `['web', 'glint-auth']`. To add additional middleware (e.g. `auth`):

```php
// config/glint.php
'middleware' => ['web', 'auth', 'glint-auth'],
```

## Publishing views

To customise the dashboard UI, publish the Blade views:

```bash
php artisan vendor:publish --tag=glint-views
```

Views are published to `resources/views/vendor/glint/`.
