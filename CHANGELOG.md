# Changelog

All notable changes to `cybernerdie/laravel-glint` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-06-09

### Added

**Core recording pipeline**
- `LlmCallStarted`, `LlmCallFinished`, `LlmToolCalled`, `LlmCallFailed` events drive all recording
- `GlintRecorder` as the single database write point, listening to all four events
- `RecordLlmCallJob` for async mode — dispatched to a dedicated queue to keep the request path fast
- `TraceContext` scoped singleton — resets per request, Octane-safe
- `GlintMiddleware` — opens a trace at the start of each HTTP request and closes it on the way out

**Auto-instrumentation drivers**
- `HttpClientInstrumentation` — universal driver that hooks into Laravel's HTTP client events; works with any LLM SDK that uses `Http::post()` internally
- `PrismInstrumentation` — wraps `PrismManager` via a decorator to trace every Prism text generation
- `NeuronAiInstrumentation` — hooks into NeuronAI's built-in observability pipeline
- `LaravelAiInstrumentation` — translates Laravel AI events (Laravel 12+) to Glint events

**Manual tracing API**
- `Glint::trace()`, `Glint::span()`, `Glint::generation()` facade methods
- Fluent `ActiveTrace`, `ActiveSpan`, `ActiveGeneration` with `tag()`, `tags()`, `end()`, `fail()`, `finish()`
- `tags(array $tags)` on all three types — writes multiple tags in a single SELECT + UPDATE instead of N round-trips
- `$trace->generation(name, callback, provider, model)` — provider and model can now be supplied at the trace level so cost tracking works correctly in callback-based flows
- Null object pattern (`NullTrace`, `NullSpan`, `NullGeneration`) — no-ops when Glint is disabled or the request is not sampled

**Pricing**
- JSON-driven `PricingRegistry` — lazy-loaded, covers OpenAI, Anthropic, Gemini, Groq, Mistral, Ollama
- Cost calculated from prompt and completion tokens at generation finish time
- `glint_pricing.json` published to `config/` on install; users edit it to add models or correct prices

**Database**
- `glint_traces` — top-level operations with ULID primary key, user/session context, status, duration
- `glint_spans` — child spans with type (`span`, `tool_call`, `retrieval`, `embedding`, `generation`, `custom`)
- `glint_generations` — individual LLM calls with provider, model, tokens, cost, finish reason, optional prompt/completion text
- `glint_aggregates` — pre-computed hourly, daily, weekly, and monthly rollups per provider/model
- `glint_quotas` — per-user and per-team token/cost budget tracking
- `glint_alert_rules` — configurable alert definitions
- `glint_alert_events` — alert delivery history
- All tables prefixed `glint_`. ULID PKs on traces/spans/generations. No FK constraints (multi-tenancy compatible).

**Alert system**
- `AlertDispatcher` evaluates rules every five minutes via scheduled task
- Four alert types: `cost_threshold`, `error_rate`, `latency_spike`, `token_spike`
- Six scope types: `global`, `user`, `team`, `provider`, `model`
- Notification delivery via mail, Slack, and webhook channels

**Dashboard**
- Built with Blade + Alpine.js — no Livewire dependency, no Filament
- Traces index and detail view (span tree with timing breakdown)
- Generations list with token counts, cost, latency, and finish reason
- Cost breakdown by provider and model
- Auth via `viewGlint` gate, modelled on Telescope's `GlintApplicationServiceProvider` pattern

**Artisan commands**
- `glint:install` — publishes config, pricing, migrations, and app service provider; runs migrations; registers provider in `bootstrap/providers.php`
- `glint:publish` — re-publishes views and assets without touching config or migrations
- `glint:prune` — deletes records beyond the configured retention window using `MassPrunable`
- `glint:clear` — truncates all Glint tables (development use)
- `glint:pricing` — displays the loaded pricing registry as a formatted table
- `glint:recalc-aggregates` — rebuilds `glint_aggregates` from raw generation data

**Testing**
- `Glint::fake()` swaps the recorder for an in-memory store; registered filters are respected by the fake
- `GlintFake` assertion methods: `assertNothingRecorded()`, `assertGenerationCount()`, `assertHasGeneration()`, `assertMissingGeneration()`, `assertGenerationSucceeded()`, `assertGenerationFailed()`, `assertHasToolCall()`, `assertToolCallCount()`, `assertNoGenerations()`, `assertNoToolCalls()`
- `FakeTrace`, `FakeSpan`, `FakeGeneration` — in-memory counterparts to the real tracing objects
- 477 tests covering all happy and unhappy paths across unit and feature suites

**Laravel Pulse integration (optional)**
- `GlintCard` Livewire card — shows today's cost, request count, error rate, and a 7-day cost sparkline
- Reads from `glint_aggregates`; no Pulse recorder configuration required
- Registered automatically when `GLINT_PULSE_ENABLED=true` and `laravel/pulse` is installed
- Add to any Pulse dashboard with `<livewire:cybernerdie.glint::glint-card cols="2" />`

**Configuration**
- Master switch (`GLINT_ENABLED`) — safe to leave `false` in non-production environments
- Sampling rate (`GLINT_SAMPLING_RATE`) — record a fraction of requests to reduce storage overhead
- Async/sync recording mode (`GLINT_MODE`)
- Body storage (`GLINT_STORE_BODIES`) — opt-in to persist raw prompt and completion text
- Configurable data retention per record type
- Privacy redaction patterns applied to stored metadata and bodies before writing to the database
- `throw_on_exceptions` flag for local debugging of Glint internals

[Unreleased]: https://github.com/cybernerdie/laravel-glint/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/cybernerdie/laravel-glint/releases/tag/v1.0.0
