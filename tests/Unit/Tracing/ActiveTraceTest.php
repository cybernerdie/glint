<?php

declare(strict_types=1);

use Carbon\Carbon;
use Cybernerdie\Glint\Context\TraceContext;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Tracing\ActiveGeneration;
use Cybernerdie\Glint\Tracing\ActiveSpan;
use Cybernerdie\Glint\Tracing\ActiveTrace;
use Illuminate\Support\Str;

function makeActiveTrace(?string $traceId = null): array
{
    $traceId ??= Str::uuid()->toString();
    GlintTrace::factory()->pending()->create(['id' => $traceId, 'name' => 'test-trace']);
    $context = app(TraceContext::class);
    $context->openTrace($traceId);
    $pricing = app(PricingRegistry::class);
    $trace = new ActiveTrace($traceId, $context, Carbon::now(), $pricing);

    return [$trace, $traceId, $context];
}

it('traceId returns the trace id', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    expect($trace->traceId())->toBe($traceId);
});

it('tag updates metadata on the trace record', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    $result = $trace->tag('env', 'testing');

    expect($result)->toBe($trace);

    $row = GlintTrace::where('id', $traceId)->first();
    expect($row->metadata['tags']['env'])->toBe('testing');
});

it('tag appends to existing tags', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    $trace->tag('first', 'a');
    $trace->tag('second', 'b');

    $row = GlintTrace::where('id', $traceId)->first();
    expect($row->metadata['tags'])->toBe(['first' => 'a', 'second' => 'b']);
});

it('tag returns self when trace row does not exist', function (): void {
    $fakeId = Str::uuid()->toString();
    $context = app(TraceContext::class);
    $context->openTrace($fakeId);
    $pricing = app(PricingRegistry::class);
    $trace = new ActiveTrace($fakeId, $context, Carbon::now(), $pricing);

    $result = $trace->tag('key', 'value');

    expect($result)->toBe($trace);
});

it('end updates the trace status to success and closes context', function (): void {
    [$trace, $traceId, $context] = makeActiveTrace();

    $trace->end();

    $row = GlintTrace::where('id', $traceId)->first();
    expect($row->status)->toBe(RecordStatus::Success)
        ->and($row->ended_at)->not->toBeNull()
        ->and($row->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('end closes the trace context', function (): void {
    [$trace, $traceId, $context] = makeActiveTrace();

    $trace->end();

    expect($context->traceId())->toBeNull();
});

it('span creates a span row and calls callback with ActiveSpan', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    $called = false;
    $result = $trace->span('my-span', function ($span) use (&$called) {
        $called = true;

        return 'span-result';
    });

    expect($called)->toBeTrue()
        ->and($result)->toBe('span-result')
        ->and(GlintSpan::where('trace_id', $traceId)->count())->toBe(1);
});

it('span auto-ends the span after callback', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    $trace->span('auto-end-span', fn ($span) => null);

    $span = GlintSpan::where('trace_id', $traceId)->first();
    expect($span->status)->toBe(RecordStatus::Success)
        ->and($span->ended_at)->not->toBeNull();
});

it('generation creates generation row and calls callback', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    $called = false;
    $result = $trace->generation('my-gen', function ($generation) use (&$called) {
        $called = true;

        return 'gen-result';
    });

    expect($called)->toBeTrue()
        ->and($result)->toBe('gen-result')
        ->and(GlintGeneration::where('trace_id', $traceId)->count())->toBe(1);
});

it('generation passes ActiveGeneration to callback', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    $genRef = null;
    $trace->generation('check-gen', function ($generation) use (&$genRef) {
        $genRef = $generation;
    });

    expect($genRef)->toBeInstanceOf(ActiveGeneration::class);
});

it('span marks span as error and re-throws when callback throws', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    expect(fn () => $trace->span('failing-span', function ($span): never {
        throw new RuntimeException('span failed');
    }))->toThrow(RuntimeException::class, 'span failed');

    $span = GlintSpan::where('trace_id', $traceId)->first();
    expect($span->status)->toBe(RecordStatus::Error)
        ->and($span->ended_at)->not->toBeNull()
        ->and($span->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('generation stores provider and model when supplied', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    $trace->generation('with-provider', fn ($gen) => null, 'anthropic', 'claude-3-5-sonnet-20241022');

    $row = GlintGeneration::where('trace_id', $traceId)->first();
    expect($row->provider)->toBe('anthropic')
        ->and($row->model)->toBe('claude-3-5-sonnet-20241022');
});

it('tags writes multiple tags in one round-trip', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    $result = $trace->tags(['env' => 'testing', 'version' => '2']);

    expect($result)->toBe($trace);

    $row = GlintTrace::where('id', $traceId)->first();
    expect($row->metadata['tags'])->toBe(['env' => 'testing', 'version' => '2']);
});

it('tags merges with existing tags', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    $trace->tag('existing', 'yes');
    $trace->tags(['new1' => 'a', 'new2' => 'b']);

    $row = GlintTrace::where('id', $traceId)->first();
    expect($row->metadata['tags'])->toBe(['existing' => 'yes', 'new1' => 'a', 'new2' => 'b']);
});

it('tags returns self on empty array without querying', function (): void {
    [$trace] = makeActiveTrace();

    expect($trace->tags([]))->toBe($trace);
});

it('generation marks generation and span as error and re-throws when callback throws', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    expect(fn () => $trace->generation('failing-gen', function ($gen): never {
        throw new RuntimeException('gen failed');
    }))->toThrow(RuntimeException::class, 'gen failed');

    $span = GlintSpan::where('trace_id', $traceId)->first();
    expect($span->status)->toBe(RecordStatus::Error)
        ->and($span->ended_at)->not->toBeNull();

    $gen = GlintGeneration::where('trace_id', $traceId)->first();
    expect($gen->status)->toBe(RecordStatus::Error)
        ->and($gen->ended_at)->not->toBeNull();
});

it('generation sets parent_span_id on the generation row', function (): void {
    [$trace, $traceId] = makeActiveTrace();

    $trace->generation('span-link-gen', fn ($gen) => null);

    $span = GlintSpan::where('trace_id', $traceId)->first();
    $gen = GlintGeneration::where('trace_id', $traceId)->first();

    expect($gen->parent_span_id)->toBe($span->id);
});

it('tags() returns self when trace row does not exist', function (): void {
    $fakeId = Str::uuid()->toString();
    $context = app(TraceContext::class);
    $context->openTrace($fakeId);
    $pricing = app(PricingRegistry::class);
    $trace = new ActiveTrace($fakeId, $context, Carbon::now(), $pricing);

    $result = $trace->tags(['env' => 'testing', 'version' => '1']);

    expect($result)->toBe($trace);
});

it('ActiveSpan tags() returns self when span row does not exist', function (): void {
    $fakeSpanId = Str::uuid()->toString();
    $span = new ActiveSpan($fakeSpanId, Carbon::now());

    $result = $span->tags(['env' => 'testing']);

    expect($result)->toBe($span);
});

it('ActiveGeneration tags() returns self when generation row does not exist', function (): void {
    $fakeGenId = Str::uuid()->toString();
    $pricing = app(PricingRegistry::class);
    $gen = new ActiveGeneration($fakeGenId, $pricing, 'openai', 'gpt-4o', Carbon::now());

    $result = $gen->tags(['env' => 'testing']);

    expect($result)->toBe($gen);
});
