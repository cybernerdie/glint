# Testing

Glint ships a first-class fake that replaces the database recorder with an in-memory store, so your tests stay fast and never touch the database.

## Setup

Call `Glint::fake()` at the start of a test. It returns a `GlintFake` instance you use to make assertions.

```php
use Cybernerdie\Glint\Facades\Glint;

$fake = Glint::fake();
```

`Glint::fake()` does three things:
1. Replaces the `GlintRecorder` listeners with in-memory `RecordingStore` listeners
2. Swaps the container binding so all code resolving `Glint` gets the fake
3. Flushes any registered filters so tests start clean

## Assertions

```php
$fake->assertNothingRecorded();                              // no generations and no tool calls

$fake->assertGenerationCount(2);                            // exactly 2 generations recorded
$fake->assertHasGeneration('openai', 'gpt-4o');            // at least one generation for this provider/model
$fake->assertMissingGeneration('openai', 'gpt-4o');        // no generation for this provider/model
$fake->assertGenerationForName('summarise');                // at least one generation with this name
$fake->assertGenerationSucceeded('openai', 'gpt-4o');      // generation completed successfully
$fake->assertGenerationFailed('anthropic', 'claude-3-5-sonnet-20241022'); // generation failed

$fake->assertHasToolCall('search_web');                     // a tool call with this name was recorded
$fake->assertToolCallCount(1);                              // exactly 1 tool call
$fake->assertNoToolCalls();                                 // no tool calls recorded
$fake->assertNoGenerations();                               // no generations recorded
```

## Inspecting recorded data

```php
$generation = $fake->generations()->first();

$generation->id;                // string (ULID)
$generation->provider;          // 'openai'
$generation->model;             // 'gpt-4o'
$generation->name;              // generation name
$generation->status;            // RecordStatus enum
$generation->promptTokens;      // int
$generation->completionTokens;  // int
$generation->costUsd;           // float
$generation->finishReason;      // 'stop' | null
$generation->errorMessage;      // null on success

$fake->toolCalls()->first()['toolName'];     // string
$fake->toolCalls()->first()['arguments'];    // array
$fake->toolCalls()->first()['durationMs'];   // int
```

## Example: testing a service that makes an LLM call

```php
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Facades\Glint;

it('records a generation when summarising a document', function () {
    $fake = Glint::fake();

    app(DocumentSummariser::class)->summarise($document);

    $fake->assertGenerationCount(1);
    $fake->assertHasGeneration('openai', 'gpt-4o');
    $fake->assertGenerationSucceeded('openai', 'gpt-4o');

    expect($fake->generations()->first()->promptTokens)->toBeGreaterThan(0);
});
```

## Example: testing that filters work

```php
it('does not record calls to the test model', function () {
    $fake = Glint::fake();
    $fake->filter(fn ($entry) => ! str_contains($entry->model, 'test'));

    // Trigger a call using a model name containing 'test'
    app(MyService::class)->callWithModel('gpt-4o-test');

    $fake->assertNothingRecorded();
});
```

## Resetting between tests

`Glint::fake()` flushes filters automatically. If you need to reset recorded data mid-test without creating a new fake:

```php
$fake->flush();
```
