# Laravel Glint

[![Tests](https://github.com/cybernerdie/laravel-glint/actions/workflows/tests.yml/badge.svg)](https://github.com/cybernerdie/laravel-glint/actions/workflows/tests.yml)
[![PHPStan](https://github.com/cybernerdie/laravel-glint/actions/workflows/phpstan.yml/badge.svg)](https://github.com/cybernerdie/laravel-glint/actions/workflows/phpstan.yml)
[![Code Style](https://github.com/cybernerdie/laravel-glint/actions/workflows/pint.yml/badge.svg)](https://github.com/cybernerdie/laravel-glint/actions/workflows/pint.yml)
[![Latest Release](https://img.shields.io/github/v/release/cybernerdie/laravel-glint)](https://github.com/cybernerdie/laravel-glint/releases)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE.md)

Built-in LLM observability for Laravel. No external services. No third-party accounts. No data leaving your infrastructure.

Laravel Glint automatically instruments your LLM calls — tracking every request, token count, cost, latency, and error — and presents them in a built-in dashboard modelled after Telescope and Horizon.

```php
use Cybernerdie\Glint\Facades\Glint;

$result = Glint::trace('chat.pipeline', function ($trace) use ($prompt) {
    $trace->tag('user_id', auth()->id());

    return $trace->generation(
        name: 'summarise',
        callback: fn ($gen) => $gen->finish($response->text, $promptTokens, $completionTokens),
        provider: 'openai',
        model: 'gpt-4o',
    );
});
```

Or let auto-instrumentation handle it — zero code required for [Prism](https://github.com/echolabsdev/prism) and any LLM SDK that uses Laravel's HTTP client.

## Features

- **Auto-instrumentation** — zero-code tracing for Prism and any SDK built on Laravel's HTTP client
- **Built-in dashboard** — traces, generations, and cost breakdowns; no external service required
- **Cost tracking** — token usage and USD cost calculated per generation from a published pricing registry
- **Alert system** — define cost, error rate, latency, and token spike thresholds; receive `GlintAlertTriggered` events
- **Sampling & filtering** — record a fraction of requests; filter programmatically with `Glint::filter()`
- **Testing fakes** — `Glint::fake()` for in-memory assertions; no database or queue required in tests
- **Octane-compatible** — `TraceContext` is a scoped binding, safe for long-running processes
- **Laravel Pulse card** — optional cost and request summary card for your Pulse dashboard

## Installation

Requires PHP 8.2+, Laravel 11/12/13, and a queue driver for the default async mode.

```bash
composer require cybernerdie/laravel-glint
php artisan glint:install
```

`glint:install` publishes the config, pricing registry, and migrations, runs them, and registers the application service provider automatically.

**Enable recording** in `.env`:

```env
GLINT_ENABLED=true
GLINT_DRIVERS=http     # or prism — see the Drivers doc
```

Glint is disabled by default so it has zero impact on environments where you haven't opted in.

**Start a queue worker** (Glint dispatches a small job per LLM call in the default `queue` mode):

```bash
php artisan queue:work --queue=glint
```

Visit `/glint` (or the path set in `GLINT_PATH`) to see your LLM calls.

### Optional: trace context per HTTP request

Register `GlintMiddleware` globally to group every LLM call under the HTTP request that triggered it:

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Cybernerdie\Glint\Middleware\GlintMiddleware::class);
})
```

Without this, individual generations are still recorded — you just won't see which request triggered which call.

## Documentation

Full documentation in the [`docs/`](docs/) directory:

- [Configuration](docs/configuration.md) — all env vars and config keys
- [Drivers](docs/drivers.md) — choosing and combining instrumentation drivers
- [Auto-Instrumentation](docs/auto-instrumentation.md) — zero-code setup for Prism and HTTP
- [Manual Tracing](docs/manual-tracing.md) — `Glint::trace()`, spans, and generations
- [Background Jobs](docs/background-jobs.md) — queue and console compatibility
- [Dashboard](docs/dashboard.md) — access control and what each page shows
- [Alerts](docs/alerts.md) — alert types, scopes, and receiving `GlintAlertTriggered`
- [Testing](docs/testing.md) — `Glint::fake()` and all assertion methods
- [Privacy & Redaction](docs/privacy.md) — redaction patterns and body storage
- [Laravel Pulse Integration](docs/pulse.md) — optional Pulse card setup

## Contributing

Contributions are welcome. Please open an issue first to discuss what you would like to change.

```bash
composer test   # Run the test suite
composer pint   # Fix code style
```

## License

Laravel Glint is open-sourced software licensed under the [MIT license](LICENSE.md).
