<?php

declare(strict_types=1);

return [

    // Master switch. When false, all Glint calls return null objects silently.
    'enabled' => env('GLINT_ENABLED', false),

    // Dashboard URL path. Falls back to 'glint' if it contains invalid characters.
    'path' => env('GLINT_PATH', 'glint'),

    // The 'glint-auth' group enforces the viewGlint gate. Register
    // GlintApplicationServiceProvider (via `php artisan glint:install`) for production.
    'middleware' => ['web', 'glint-auth'],

    // Emails allowed by the default viewGlint gate. Override gate() for custom logic.
    'admin_emails' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('GLINT_ADMIN_EMAILS', ''))),
        static fn (string $e) => $e !== ''
    )),

    // Active instrumentation drivers: "prism", "laravel-ai", "http", "neuron-ai",
    // or a custom driver FQCN. "http" is the universal fallback.
    'drivers' => array_values(array_filter(
        explode(',', (string) (env('GLINT_DRIVERS') ?: 'http')),
        static fn (string $d) => trim($d) !== ''
    )),

    'recording' => [
        // 'queue' dispatches a job (recommended); 'sync' writes inline.
        'mode' => env('GLINT_MODE', 'queue'),

        // Fraction of requests to record (clamped to 0.0–1.0).
        'sampling_rate' => max(0.0, min(1.0, (float) env('GLINT_SAMPLING_RATE', 1.0))),

        // Store raw prompt and completion text. Disable for privacy.
        'store_bodies' => env('GLINT_STORE_BODIES', false),

        // Truncate stored completion/error text. 0 disables the limit.
        'max_completion_chars' => (int) env('GLINT_MAX_COMPLETION_CHARS', 65535),
    ],

    'queue' => [
        'connection' => env('GLINT_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
        'queue' => env('GLINT_QUEUE'),
    ],

    'retention' => [
        'traces_days' => (int) env('GLINT_RETENTION_TRACES', 30),
        'aggregates_days' => (int) env('GLINT_RETENTION_AGGREGATES', 365),
        'alert_days' => (int) env('GLINT_RETENTION_ALERTS', 90),
    ],

    // Path to the token pricing JSON. Published to config/ on glint:install.
    'pricing_path' => env('GLINT_PRICING_PATH', config_path('glint_pricing.json')),

    // Let internal exceptions propagate instead of being swallowed. For debugging.
    'throw_on_exceptions' => env('GLINT_THROW_ON_EXCEPTIONS', false),

    'privacy' => [
        // Set true to store the requester's IP. Off by default for GDPR.
        'store_ip' => env('GLINT_STORE_IP', false),

        // Matching values in metadata and bodies are replaced with [REDACTED].
        'redact_patterns' => [
            '/api[_-]?key["\s:=]+([a-zA-Z0-9_\-]+)/i',
            '/bearer\s+([a-zA-Z0-9_\-\.]+)/i',
            '/sk-[a-zA-Z0-9]{32,}/i',
        ],
    ],

    // Outgoing requests to these hosts are recorded as LLM generations by the "http" driver.
    'llm_hosts' => [
        'api.openai.com' => 'openai',
        'api.anthropic.com' => 'anthropic',
        'generativelanguage.googleapis.com' => 'gemini',
        'api.groq.com' => 'groq',
        'api.mistral.ai' => 'mistral',
        'openrouter.ai' => 'openrouter',
        'localhost:11434' => 'ollama',
        '127.0.0.1:11434' => 'ollama',
    ],

    // Register the Glint Pulse card: <livewire:glint-card cols="2" />. Requires laravel/pulse.
    'pulse' => [
        'enabled' => env('GLINT_PULSE_ENABLED', false),
    ],

];
