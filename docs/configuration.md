# Configuration

After installation, the full configuration file is available at `config/glint.php`. Every option can be controlled via environment variables.

## Reference

| Key | Env var | Default | Description |
|-----|---------|---------|-------------|
| `enabled` | `GLINT_ENABLED` | `false` | Master switch. Set to `true` to start recording. |
| `path` | `GLINT_PATH` | `glint` | URL path for the dashboard (`/glint`). |
| `admin_emails` | `GLINT_ADMIN_EMAILS` | *(empty)* | Comma-separated emails allowed by the default `viewGlint` gate. |
| `drivers` | `GLINT_DRIVERS` | `http` | Comma-separated list of active instrumentation drivers. Accepts built-in names (`http`, `prism`, `laravel-ai`, `neuron-ai`) or a custom driver FQCN. |
| `recording.mode` | `GLINT_MODE` | `queue` | `queue` dispatches a job; `sync` writes inline. |
| `recording.store_bodies` | `GLINT_STORE_BODIES` | `true` | Store raw prompt and completion text. Set `false` for metadata-only recording. |
| `recording.max_completion_chars` | `GLINT_MAX_COMPLETION_CHARS` | `65535` | Truncate stored completion/error text to this many characters. `0` disables the limit. |
| `queue.connection` | `GLINT_QUEUE_CONNECTION` | `QUEUE_CONNECTION` | Queue connection for recording jobs. |
| `queue.queue` | `GLINT_QUEUE` | *(default queue)* | Named queue for recording jobs. Set to `glint` to isolate from application jobs. |
| `retention.traces_days` | `GLINT_RETENTION_TRACES` | `30` | Days to keep traces, spans, and generations. |
| `retention.aggregates_days` | `GLINT_RETENTION_AGGREGATES` | `365` | Days to keep aggregate statistics. |
| `retention.alert_days` | `GLINT_RETENTION_ALERTS` | `90` | Days to keep alert history. |
| `pricing_path` | `GLINT_PRICING_PATH` | `config/glint_pricing.json` | Path to the token pricing JSON file. |
| `pricing_overrides` | — | `[]` | Provider/model price overrides in USD per 1M tokens. |
| `throw_on_exceptions` | `GLINT_THROW_ON_EXCEPTIONS` | `false` | Let internal Glint exceptions propagate. Useful for debugging. |
| `privacy.store_ip` | `GLINT_STORE_IP` | `false` | Set `true` to store the requester's IP in trace metadata. Left off by default for GDPR compliance. |
| `pulse.enabled` | `GLINT_PULSE_ENABLED` | `false` | Register the Glint Pulse dashboard card. |

## Recording mode

**`queue` (default)** — Glint dispatches a `RecordLlmCallJob` for every LLM event. The DB write happens asynchronously in a queue worker. Use this in production when queue workers are available.

**`sync`** — Glint writes to the database immediately, in the same process as the request. Useful for local development or when you don't have a queue configured.

```env
GLINT_MODE=queue   # default
GLINT_MODE=sync    # immediate writes
```

## Queue isolation

By default Glint jobs are pushed to your application's default queue. To isolate them on a dedicated queue:

```env
GLINT_QUEUE=glint
```

Make sure a worker consumes that queue:

```bash
php artisan queue:work --queue=glint
```

## Storing prompt and completion text

By default Glint stores prompt messages and completion text so generation details are useful during debugging and review. To record only metadata (tokens, cost, latency, model), disable body storage:

```env
GLINT_STORE_BODIES=false
```

Privacy redaction patterns are applied before writing. See [Privacy](privacy.md).

## Data retention

Configure how long raw data is kept before `glint:prune` removes it:

```env
GLINT_RETENTION_TRACES=30     # days for traces/spans/generations
GLINT_RETENTION_AGGREGATES=365 # days for aggregate stats
GLINT_RETENTION_ALERTS=90     # days for alert events
```

Schedule the prune command at the cadence that matches your traffic volume:

```php
// routes/console.php
Schedule::command('glint:prune')->daily();
```

For low-volume applications, daily pruning is usually enough. For high-volume applications, schedule pruning more frequently and keep raw trace retention short enough that dashboard queries stay fast. Aggregates are smaller than raw traces/generations, so they can usually be retained longer.

Glint follows the same operational model as Laravel Telescope: it stores its tables in your application database and expects the application owner to decide how often pruning should run. If you need hard isolation for observability data, use your normal Laravel and database deployment tools to provision that isolation.

## Pricing registry

Glint calculates cost from the published pricing registry at `config/glint_pricing.json`. Provider prices can change, and new model names may appear before the bundled registry is updated.

Use `pricing_overrides` for private models, newly released models, or provider price changes:

```php
// config/glint.php
'pricing_overrides' => [
    'openai' => [
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00],
    ],
    'custom-provider' => [
        'my-model' => ['input' => 1.00, 'output' => 3.00],
    ],
],
```

Prices are USD per 1 million tokens. Overrides win over the published JSON file.

To find recorded models that are missing prices:

```bash
php artisan glint:pricing --unknown
```
