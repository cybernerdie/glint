# Drivers

A driver is the mechanism that detects outgoing LLM calls and fires the internal Glint events. Set one or more drivers via `GLINT_DRIVERS` (comma-separated).

## Available drivers

| Driver | Package | Status |
|--------|---------|--------|
| `http` | *(built-in)* | ✅ Ready |
| `prism` | `echolabsdev/prism` | ✅ Ready |
| `neuron-ai` | `useiconic/neuron-ai` | ✅ Ready |
| `laravel-ai` | `illuminate/ai` (Laravel 12+) | ✅ Ready |

---

## `http` — Universal driver

Hooks into Laravel's HTTP client events (`RequestSending`, `ResponseReceived`). Any AI SDK that uses `Http::post()` internally is automatically captured — including `laravel/ai`, most OpenAI wrappers, and any custom HTTP integration.

**Use this if you are unsure which driver to pick.** It works with every SDK.

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

Wraps Prism's provider manager with a decorator at the SDK layer. Captures structured message arrays, tool call details, and token counts directly — more reliable than parsing raw HTTP bodies.

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

Glint deduplicates by `generationId` so a single LLM call is never recorded twice even if two drivers both see it.

---

## `laravel-ai` — Laravel AI driver

Listens to `laravel/ai`'s native events (`PromptingAgent`, `AgentPrompted`, `StreamingAgent`, `AgentStreamed`, `InvokingTool`, `ToolInvoked`). Captures structured token counts, tool call details, and completion text directly from the SDK — no HTTP body parsing required.

Requires `illuminate/ai` (shipped with Laravel 12+).

```env
GLINT_DRIVERS=laravel-ai
```

Provider names are resolved automatically from the provider class name (e.g. `OpenAiProvider` → `openai`, `AnthropicProvider` → `anthropic`). Tool calls are captured as `ToolCall` spans linked to their parent generation.

---

---

## `neuron-ai` — NeuronAI driver

Registers a global observer with NeuronAI's `EventBus`. Every agent that calls `EventBus::emit()` (which all standard NeuronAI agents do automatically) is traced without any code changes to your agents.

Captures inference start/stop, tool calls, RAG retrieval, and errors. Token counts are taken directly from the response `Usage` object.

Requires `useiconic/neuron-ai` to be installed.

```env
GLINT_DRIVERS=neuron-ai
```

Provider name and model are resolved via reflection on the agent's internal provider object (e.g. `OpenAI` → `openai`).

