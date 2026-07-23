# Privacy & Redaction

## Prompt and completion storage

By default Glint stores prompt messages and completion text so generation details can show the actual LLM input and output.

To record only metadata (model, tokens, cost, latency, status), disable body storage:

```env
GLINT_STORE_BODIES=false
```

Glint applies redaction patterns before writing bodies to the database.

## IP address

The requester's IP address is not stored by default. Enable it only when you need it and your privacy policy allows it:

```env
GLINT_STORE_IP=true
```

## Redaction patterns

Glint applies regex patterns before persisting prompts, completions, tool input/output, metadata, middleware response bodies, User-Agent strings, and error messages. The default patterns strip API keys, bearer tokens, and OpenAI secret keys:

```php
// config/glint.php
'privacy' => [
    'store_ip' => env('GLINT_STORE_IP', false),
    'redact_patterns' => [
        '/api[_-]?key["\s:=]+([a-zA-Z0-9_\-]+)/i',
        '/bearer\s+([a-zA-Z0-9_\-\.]+)/i',
        '/sk-[a-zA-Z0-9]{32,}/i',
    ],
],
```

Matches are replaced with `[REDACTED]`. Add your own patterns to the array:

```php
'redact_patterns' => [
    // default patterns...
    '/my-internal-secret-\w+/i',
    '/ssn:\s*\d{3}-\d{2}-\d{4}/i',
],
```

Invalid regex patterns (that fail to compile) are silently skipped and logged at `debug` level — a bad pattern never crashes the application.

## Data retention

Configure how long raw data is kept:

```env
GLINT_RETENTION_TRACES=30       # days for traces, spans, and generations
GLINT_RETENTION_AGGREGATES=365  # days for aggregate statistics
GLINT_RETENTION_ALERTS=90       # days for alert event history
```

Schedule `glint:prune` to run daily to enforce these limits:

```php
// routes/console.php
Schedule::command('glint:prune')->daily();
```
