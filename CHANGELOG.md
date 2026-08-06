# Changelog

All notable changes to `cybernerdie/glint` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.1] - 2026-08-06

### Fixed

- Prism driver: calls to `structured()`, `embeddings()`, `images()`, `moderation()`, `textToSpeech()`, `speechToText()`, and `stream()` on a traced provider no longer fail with `"X is not supported by TracingProvider"`. These are now correctly delegated to the wrapped provider, same as `text()`.
- Prism driver: `structured()` and `embeddings()` now fire `LlmCallStarted`, `LlmCallFinished`, and `LlmCallFailed` events, so calls using those action types are recorded in Glint with full trace, cost, and latency data.
- Prism driver: `TracingPrismManager::extend()` parameter type corrected from `callable` to `Closure` to match the real `PrismManager` signature, preventing a fatal declaration error on boot.
- Dashboard: version display in the sidebar no longer throws `OutOfBoundsException` when the package is not registered in Composer's installed manifest (e.g. during package development).

## [1.0.0] - 2026-08-02

Initial release as `cybernerdie/glint` (renamed from `cybernerdie/laravel-glint`).

### Added

- Auto-instrumentation for the Laravel HTTP client, Prism, Laravel AI, and Neuron AI
- Manual tracing API via the `Glint` facade: `trace()`, `span()`, and `generation()`
- Token and cost tracking with a published JSON pricing registry (OpenAI, Anthropic, Google, Groq, Mistral, Ollama)
- Dashboard at `/glint` — traces, generations, cost, latency, users, and alerts
- Alert rules for cost threshold, error rate, latency spike, and token spike; delivered via mail, Slack, or webhook
- `Glint::fake()` testing helper with assertion methods for generations and tool calls
- Optional Laravel Pulse card showing daily cost, request count, and error rate
- Artisan commands: `glint:install`, `glint:publish`, `glint:prune`, `glint:clear`, `glint:verify`, `glint:pricing`, `glint:recalc-aggregates`, `glint:dispatch-alerts`
- Sync and queue recording modes (`GLINT_MODE`)
- Privacy redaction for prompts, completions, and metadata before storage
- Laravel Octane compatibility

[Unreleased]: https://github.com/cybernerdie/glint/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/cybernerdie/glint/releases/tag/v1.0.0
