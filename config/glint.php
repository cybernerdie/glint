<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Glint Master Switch
    |--------------------------------------------------------------------------
    | Disable entirely in environments where you don't want any recording.
    | When disabled, all Glint calls return null objects silently.
    */
    'enabled' => env('GLINT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Path & Middleware
    |--------------------------------------------------------------------------
    | The path where the Glint dashboard will be accessible.
    |
    | SECURITY: The 'glint-auth' middleware group is automatically added by
    | GlintApplicationServiceProvider and enforces the 'viewGlint' gate on
    | every dashboard route. You MUST publish and register the
    | GlintApplicationServiceProvider (via `php artisan glint:install`) or add
    | your own auth middleware here for production use.
    |
    | The path value must only contain alphanumeric characters, hyphens,
    | underscores, and forward slashes. Any other value falls back to 'glint'.
    */
    'path' => env('GLINT_PATH', 'glint'),

    'middleware' => ['web', 'glint-auth'],

    /*
    |--------------------------------------------------------------------------
    | Auto-Instrumentation Drivers
    |--------------------------------------------------------------------------
    | Enable the drivers that match the LLM packages in your application.
    | Multiple drivers can be active simultaneously.
    |
    | Available: "prism", "laravel-ai", "http", "neuron-ai"
    |
    | The "http" driver is the universal fallback and works with any LLM SDK
    | that uses Laravel's HTTP client internally.
    */
    // env() returns null when the var is present-but-blank; the ?: 'http' fallback
    // ensures explode() always receives a non-empty string, and array_filter removes
    // any blank tokens that result from double-commas or a trailing comma.
    'drivers' => array_values(array_filter(
        explode(',', (string) (env('GLINT_DRIVERS') ?: 'http')),
        static fn (string $d) => trim($d) !== ''
    )),

    /*
    |--------------------------------------------------------------------------
    | Recording Settings
    |--------------------------------------------------------------------------
    */
    'recording' => [
        // 'queue' dispatches a job to your queue — recommended (zero latency impact).
        // 'sync'  writes to the database immediately, inline with the request.
        'mode' => env('GLINT_MODE', 'queue'),

        // 1.0 = record every request. 0.1 = record 10% of requests.
        // Values > 1.0 behave identically to 1.0 (always sample).
        // Values < 0.0 behave identically to 0.0 (never sample).
        // The shouldSample() method clamps the value, but setting it outside
        // [0.0, 1.0] is almost certainly a misconfiguration.
        'sampling_rate' => max(0.0, min(1.0, (float) env('GLINT_SAMPLING_RATE', 1.0))),

        // When true, stores the raw prompt messages and completion text.
        // Disable for privacy or to reduce database storage usage.
        'store_bodies' => env('GLINT_STORE_BODIES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Settings (queue mode only)
    |--------------------------------------------------------------------------
    | connection — which queue connection to use. Defaults to your app's
    |              QUEUE_CONNECTION so it works out of the box.
    | queue      — which named queue to push jobs onto. Defaults to null
    |              (the connection's default queue). Set GLINT_QUEUE=glint
    |              to isolate Glint jobs from your application jobs.
    */
    'queue' => [
        'connection' => env('GLINT_QUEUE_CONNECTION', env('QUEUE_CONNECTION')),
        'queue' => env('GLINT_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    | Days to retain raw traces, spans, and generations before pruning.
    | Aggregates are retained longer for trend analysis.
    */
    'retention' => [
        'traces_days' => (int) env('GLINT_RETENTION_TRACES', 30),
        'aggregates_days' => (int) env('GLINT_RETENTION_AGGREGATES', 365),
        'alert_days' => (int) env('GLINT_RETENTION_ALERTS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Registry
    |--------------------------------------------------------------------------
    | Path to the JSON pricing file. Published to config/ on glint:install.
    | Prices are in USD per 1 million tokens.
    | Submit PRs to pricing/providers.json to add or update model prices.
    */
    'pricing_path' => env('GLINT_PRICING_PATH', config_path('glint_pricing.json')),

    /*
    |--------------------------------------------------------------------------
    | Exception Handling
    |--------------------------------------------------------------------------
    | By default Glint silently swallows all internal exceptions so it can
    | never crash the host application. Set this to true (e.g. in local/testing
    | environments) to let exceptions propagate — useful for debugging Glint
    | itself or writing integration tests.
    */
    'throw_on_exceptions' => env('GLINT_THROW_ON_EXCEPTIONS', false),

    /*
    |--------------------------------------------------------------------------
    | Privacy & Redaction
    |--------------------------------------------------------------------------
    | Regex patterns applied to stored metadata and bodies before writing to
    | the database. Matching values are replaced with [REDACTED].
    |
    | store_ip — set to false to omit the requester's IP address from trace
    | metadata. Useful in GDPR/privacy-conscious environments where IP
    | addresses are considered personal data.
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
    | HTTP Client Instrumentation — Known LLM Hosts
    |--------------------------------------------------------------------------
    | The "http" driver watches outgoing requests to these base URLs and
    | automatically records them as LLM generations.
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
    | Laravel Pulse Integration
    |--------------------------------------------------------------------------
    | When enabled, registers the Glint Pulse card so it can be added to your
    | Pulse dashboard. Requires laravel/pulse to be installed.
    |
    | Usage — add to your Pulse dashboard view:
    |   <livewire:cybernerdie.glint::glint-card cols="2" />
    |
    | The card reads from glint_aggregates, so Glint recording must have been
    | active for data to appear.
    */
    'pulse' => [
        'enabled' => env('GLINT_PULSE_ENABLED', false),
    ],

];
