# Glint for Laravel

[![Tests](https://github.com/cybernerdie/glint/actions/workflows/tests.yml/badge.svg)](https://github.com/cybernerdie/glint/actions/workflows/tests.yml)
[![PHPStan](https://github.com/cybernerdie/glint/actions/workflows/phpstan.yml/badge.svg)](https://github.com/cybernerdie/glint/actions/workflows/phpstan.yml)
[![Code Style](https://github.com/cybernerdie/glint/actions/workflows/pint.yml/badge.svg)](https://github.com/cybernerdie/glint/actions/workflows/pint.yml)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

Glint is an LLM observability package for Laravel applications. It records LLM calls in your application database and provides a local dashboard for traces, generations, cost, latency, errors, users, and alerts.

Glint is designed for teams that want Laravel-native observability without sending prompts, completions, or usage data to a third-party service.

## Requirements

- PHP 8.3+
- Laravel 11, 12, or 13
- A configured queue worker when using the default `queue` recording mode

## Supported instrumentation

Glint can record LLM calls through:

- Laravel HTTP client requests to configured LLM hosts
- [Prism](https://github.com/echolabsdev/prism)
- [Laravel AI](https://github.com/laravel/ai)
- [Neuron AI](https://docs.neuron-ai.dev/)
- Manual tracing with the `Glint` facade

SDKs or transports that do not use one of the supported instrumentation paths should be wrapped with manual tracing.

## Features

- Local dashboard for traces, generations, costs, users, latency, and alerts
- Automatic instrumentation for supported drivers
- Manual tracing API for custom or unsupported LLM clients
- Token and cost tracking from a published pricing registry
- App-level pricing overrides for private models or provider price changes
- Alert rules for cost, error rate, latency, and token usage
- Privacy redaction before data is stored
- Retention and pruning commands
- Queue and sync recording modes
- `Glint::fake()` testing utilities
- Optional Laravel Pulse card

## Installation

Install the package and run the installer:

```bash
composer require cybernerdie/glint
php artisan glint:install
```

The installer publishes the config, pricing registry, migrations, and application service provider, then runs the migrations.

Enable recording:

```env
GLINT_ENABLED=true
GLINT_DRIVERS=http
```

Visit `/glint` to open the dashboard.

By default, Glint records asynchronously through Laravel's queue. If your application already runs queue workers, no separate worker is required. To isolate Glint recording jobs, set a dedicated queue and run a worker for it:

```env
GLINT_QUEUE=glint
```

```bash
php artisan queue:work --queue=glint
```

## HTTP request traces

LLM generations are recorded without middleware. To group generations under the HTTP request that triggered them, register `GlintMiddleware` globally:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Cybernerdie\Glint\Middleware\GlintMiddleware::class);
})
```

## Manual tracing

Use manual tracing when auto-instrumentation cannot see a call or when you want to add application-specific context:

```php
use Cybernerdie\Glint\Facades\Glint;

$trace = Glint::trace('chat.pipeline', [
    'user_id' => (string) auth()->id(),
]);

try {
    $generation = Glint::generation('summarise', 'openai', 'gpt-4o');
    $generation
        ->prompt($prompt)
        ->options(temperature: 0.7, maxTokens: 1024, topP: 0.9);

    $response = $client->chat($prompt);

    $generation->finish(
        completion: $response->text,
        promptTokens: $response->promptTokens,
        completionTokens: $response->completionTokens,
    );
} catch (\Throwable $e) {
    isset($generation) && $generation->fail($e);

    throw $e;
} finally {
    $trace->end();
}
```

## Configuration

The main configuration file is published to `config/glint.php`.

Common options:

- `GLINT_ENABLED` — enable or disable recording
- `GLINT_DRIVERS` — comma-separated instrumentation drivers
- `GLINT_MODE` — `queue` or `sync`
- `GLINT_QUEUE` — queue name for recording jobs
- `GLINT_STORE_BODIES` — store prompts and completions; set `false` for metadata-only recording
- `GLINT_STORE_IP` — opt in to storing requester IP addresses
- `GLINT_RETENTION_TRACES` — raw trace/generation retention
- `GLINT_RETENTION_AGGREGATES` — aggregate retention
- `GLINT_RETENTION_ALERTS` — alert event retention

See [Configuration](docs/configuration.md) for the full reference.

## Documentation

- [Configuration](docs/configuration.md)
- [Drivers](docs/drivers.md)
- [Auto-Instrumentation](docs/auto-instrumentation.md)
- [Manual Tracing](docs/manual-tracing.md)
- [Background Jobs](docs/background-jobs.md)
- [Dashboard](docs/dashboard.md)
- [Alerts](docs/alerts.md)
- [Exporting](docs/exporting.md)
- [Testing](docs/testing.md)
- [Privacy & Redaction](docs/privacy.md)
- [Laravel Pulse Integration](docs/pulse.md)

## Testing

```bash
composer test
composer stan
composer pint
```

## License

Glint is open-sourced software licensed under the [MIT license](LICENSE.md).
