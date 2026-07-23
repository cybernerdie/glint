# Drivers

A driver is the mechanism that detects outgoing LLM calls and fires the internal Glint events. Set one or more drivers via `GLINT_DRIVERS` (comma-separated).

## Available drivers

| Driver | Package | Status |
|--------|---------|--------|
| `http` | *(built-in)* | ✅ Ready |
| `prism` | `echolabsdev/prism` | ✅ Ready |
| `neuron-ai` | `useiconic/neuron-ai` | ✅ Ready |
| `laravel-ai` | `laravel/ai` | ✅ Ready |

---

## `http` — Laravel HTTP client

Hooks into Laravel's HTTP client events (`RequestSending`, `ResponseReceived`). LLM calls that use Laravel's HTTP client are captured, including custom integrations built on the `Http` facade.

Use this when your application sends LLM requests through Laravel's `Http` facade/client, or through a package that internally uses Laravel's HTTP client.

This driver does not see HTTP traffic sent through unrelated clients such as raw Guzzle clients, PSR-18 clients, cURL, or official SDKs that bring their own transport layer. For those paths, use a native Glint driver when one exists, or wrap the call with [manual tracing](manual-tracing.md).

```env
GLINT_DRIVERS=http
```

Known LLM hosts are configured in `config/glint.php`. Glint only records calls to these hosts, ignoring all other outgoing HTTP requests:

```php
'llm_hosts' => [
    'api.openai.com'                      => 'openai',
    'api.anthropic.com'                   => 'anthropic',
    'generativelanguage.googleapis.com'   => 'gemini',
    'api.groq.com'                        => 'groq',
    'api.mistral.ai'                      => 'mistral',
    'openrouter.ai'                       => 'openrouter',
    'localhost:11434'                     => 'ollama',
    '127.0.0.1:11434'                    => 'ollama',
],
```

To add a custom host (e.g. a proxy or a self-hosted model):

```php
// config/glint.php
'llm_hosts' => [
    ...
    'my-llm-proxy.internal' => 'openai',
],
```

---

## `prism` — Prism SDK driver

Wraps Prism's provider manager at the SDK layer. Captures structured message arrays, token counts, request options, completion text, and errors without parsing raw HTTP bodies.

Requires `echolabsdev/prism` to be installed.

```env
GLINT_DRIVERS=prism
```

---

## Running multiple drivers

You can run multiple drivers simultaneously. For example, if your app uses both Prism and raw HTTP calls:

```env
GLINT_DRIVERS=http,prism
```

Glint emits a deterministic request fingerprint from driver metadata and uses it to deduplicate overlapping observations while the call is in flight. This prevents a single LLM call from being recorded twice when, for example, both `http` and `prism` see the same request.

Deduplication is intentionally scoped to pending calls. If your application sends the same prompt/model again as a separate later request, Glint records it as a separate generation.

---

## `laravel-ai` — Laravel AI driver

Listens to `laravel/ai`'s native events (`PromptingAgent`, `AgentPrompted`, `StreamingAgent`, `AgentStreamed`, `InvokingTool`, `ToolInvoked`). Captures structured token counts, tool call details, and completion text directly from the SDK — no HTTP body parsing required.

Requires `laravel/ai`.

```env
GLINT_DRIVERS=laravel-ai
```

Provider names are resolved automatically from the provider class name (e.g. `OpenAiProvider` → `openai`, `AnthropicProvider` → `anthropic`). Tool calls are captured as `ToolCall` spans linked to their parent generation.

---

## `neuron-ai` — NeuronAI driver

Registers a global observer with NeuronAI's `EventBus`. Standard NeuronAI agents that emit observability events are traced without changes to the agent code.

Captures inference start/stop, tool calls, RAG retrieval, and errors. Token counts are taken directly from the response `Usage` object.

Requires `useiconic/neuron-ai` to be installed.

```env
GLINT_DRIVERS=neuron-ai
```

Provider name and model are resolved via reflection on the agent's internal provider object (e.g. `OpenAI` → `openai`).

---

## Unsupported SDKs and custom clients

Glint ships native drivers for Laravel HTTP client, Prism, Laravel AI, and NeuronAI. Calls made through other transports are not automatically captured unless the SDK itself uses Laravel's HTTP client.

Common examples that may need manual tracing or a future native driver:

- official provider SDKs that use their own PSR/Guzzle transport;
- direct Guzzle or PSR-18 clients;
- custom cURL integrations;
- streaming clients where token usage is only known after the final stream event.

For these cases, use `Glint::trace()` / `Glint::generation()` around the call so the dashboard still receives tokens, cost, latency, and error status. See [Manual Tracing](manual-tracing.md).
