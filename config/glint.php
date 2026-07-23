<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for Glint. When set to false, all tracing calls return
    | null objects silently and nothing is written to the database.
    |
    */
    'enabled' => env('GLINT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Path
    |--------------------------------------------------------------------------
    |
    | The URI path where the Glint dashboard will be accessible. You may
    | change this to any value that suits your application.
    |
    */
    'path' => env('GLINT_PATH', 'glint'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Middleware
    |--------------------------------------------------------------------------
    |
    | The middleware applied to every Glint dashboard route. The 'glint-auth'
    | group enforces the viewGlint gate. Run `php artisan glint:install` to
    | publish GlintApplicationServiceProvider and configure production access.
    |
    */
    'middleware' => ['web', 'glint-auth'],

    /*
    |--------------------------------------------------------------------------
    | Admin Emails
    |--------------------------------------------------------------------------
    |
    | A comma-separated list of email addresses that are granted access to
    | the dashboard by the default viewGlint gate. Override the gate() method
    | in GlintApplicationServiceProvider for custom authorization logic.
    |
    */
    'admin_emails' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('GLINT_ADMIN_EMAILS', ''))),
        static fn (string $e) => $e !== ''
    )),

    /*
    |--------------------------------------------------------------------------
    | Instrumentation Drivers
    |--------------------------------------------------------------------------
    |
    | The active auto-instrumentation drivers. Supported values: "prism",
    | "laravel-ai", "neuron-ai", "http", or the fully-qualified class name of
    | a custom driver. The "http" driver is a universal fallback that captures
    | outgoing HTTP requests to any host listed in llm_hosts below.
    |
    */
    'drivers' => array_values(array_filter(
        explode(',', (string) (env('GLINT_DRIVERS') ?: 'http')),
        static fn (string $d) => trim($d) !== ''
    )),

    /*
    |--------------------------------------------------------------------------
    | Recording
    |--------------------------------------------------------------------------
    |
    | Controls how and what Glint records. Use "queue" mode (recommended) to
    | dispatch a background job for each LLM call, or "sync" to write inline.
    | Store bodies only when needed, and redact sensitive data via the privacy
    | settings below.
    |
    */
    'recording' => [
        'mode' => env('GLINT_MODE', 'queue'),
        'store_bodies' => env('GLINT_STORE_BODIES', false),
        'max_completion_chars' => (int) env('GLINT_MAX_COMPLETION_CHARS', 65535),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | The connection and queue name used when recording mode is set to "queue".
    | Defaults to your application's default queue connection and queue.
    |
    */
    'queue' => [
        'connection' => env('GLINT_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
        'queue' => env('GLINT_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | The number of days Glint retains data before it is pruned. Run the
    | `glint:prune` command (or schedule it daily) to enforce these limits.
    |
    */
    'retention' => [
        'traces_days' => (int) env('GLINT_RETENTION_TRACES', 30),
        'aggregates_days' => (int) env('GLINT_RETENTION_AGGREGATES', 365),
        'alert_days' => (int) env('GLINT_RETENTION_ALERTS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Path
    |--------------------------------------------------------------------------
    |
    | The path to the JSON file that maps model names to per-token costs.
    | This file is published to your config directory during `glint:install`.
    |
    */
    'pricing_path' => env('GLINT_PRICING_PATH', config_path('glint_pricing.json')),

    /*
    |--------------------------------------------------------------------------
    | Throw on Exceptions
    |--------------------------------------------------------------------------
    |
    | By default Glint swallows internal errors so it never disrupts your
    | application. Set this to true to let exceptions propagate, which is
    | useful when debugging a Glint integration.
    |
    */
    'throw_on_exceptions' => env('GLINT_THROW_ON_EXCEPTIONS', false),

    /*
    |--------------------------------------------------------------------------
    | Privacy
    |--------------------------------------------------------------------------
    |
    | Controls what personally identifiable information Glint stores. IP
    | storage is disabled by default for GDPR compliance. Any value in
    | metadata or stored bodies matching a redact pattern will be replaced
    | with [REDACTED] before it is written to the database.
    |
    */
    'privacy' => [
        'store_ip' => env('GLINT_STORE_IP', false),
        'redact_patterns' => [
            '/api[_-]?key["\s:=]+([a-zA-Z0-9_\-]+)/i',
            '/bearer\s+([a-zA-Z0-9_\-\.]+)/i',
            '/sk-[a-zA-Z0-9]{32,}/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Hosts
    |--------------------------------------------------------------------------
    |
    | Outgoing HTTP requests to these hostnames are captured by the "http"
    | driver and recorded as LLM generations. Add any custom or self-hosted
    | provider endpoints here, mapping hostname to provider name.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Pulse Integration
    |--------------------------------------------------------------------------
    |
    | When enabled, Glint provides a Laravel Pulse card that surfaces LLM
    | usage at a glance. Add the card to your Pulse dashboard with:
    | <livewire:glint-card cols="2" />. Requires laravel/pulse.
    |
    */
    'pulse' => [
        'enabled' => env('GLINT_PULSE_ENABLED', false),
    ],

];
