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
| `recording.sampling_rate` | `GLINT_SAMPLING_RATE` | `1.0` | Fraction of HTTP requests to trace (0.0–1.0). Does not affect background jobs. |
| `recording.store_bodies` | `GLINT_STORE_BODIES` | `false` | Store raw prompt and completion text. |
| `recording.max_completion_chars` | `GLINT_MAX_COMPLETION_CHARS` | `65535` | Truncate stored completion/error text to this many characters. `0` disables the limit. |
| `queue.connection` | `GLINT_QUEUE_CONNECTION` | `QUEUE_CONNECTION` | Queue connection for recording jobs. |
| `queue.queue` | `GLINT_QUEUE` | *(default queue)* | Named queue for recording jobs. Set to `glint` to isolate from application jobs. |
| `retention.traces_days` | `GLINT_RETENTION_TRACES` | `30` | Days to keep traces, spans, and generations. |
| `retention.aggregates_days` | `GLINT_RETENTION_AGGREGATES` | `365` | Days to keep aggregate statistics. |
| `retention.alert_days` | `GLINT_RETENTION_ALERTS` | `90` | Days to keep alert history. |
| `pricing_path` | `GLINT_PRICING_PATH` | `config/glint_pricing.json` | Path to the token pricing JSON file. |
| `throw_on_exceptions` | `GLINT_THROW_ON_EXCEPTIONS` | `false` | Let internal Glint exceptions propagate. Useful for debugging. |
| `privacy.store_ip` | `GLINT_STORE_IP` | `false` | Set `true` to store the requester's IP in trace metadata. Left off by default for GDPR compliance. |
| `pulse.enabled` | `GLINT_PULSE_ENABLED` | `false` | Register the Glint Pulse dashboard card. |

## Recording mode

**`queue` (default)** — Glint dispatches a `RecordLlmCallJob` for every LLM event. The DB write happens asynchronously in a queue worker. Recommended for production: zero latency impact on your application.

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

Then run a dedicated worker:

```bash
php artisan queue:work --queue=glint
```

## Sampling

`sampling_rate` controls what fraction of **HTTP requests** are traced. A value of `0.1` records 10% of requests.

```env
GLINT_SAMPLING_RATE=0.1
```

> **Note:** Sampling only applies to requests processed by `GlintMiddleware`. LLM calls inside queued jobs and console commands are always recorded. See [Background Jobs](background-jobs.md).

## Storing prompt and completion text

By default Glint only records metadata (tokens, cost, latency, model). To also store the raw prompt messages and completion text:

```env
GLINT_STORE_BODIES=true
```

Privacy redaction patterns are applied before writing. See [Privacy](privacy.md).

## Data retention

Configure how long raw data is kept before `glint:prune` removes it:

```env
GLINT_RETENTION_TRACES=30     # days for traces/spans/generations
GLINT_RETENTION_AGGREGATES=365 # days for aggregate stats
GLINT_RETENTION_ALERTS=90     # days for alert events
```

Schedule the prune command daily:

```php
// routes/console.php
Schedule::command('glint:prune')->daily();
```
