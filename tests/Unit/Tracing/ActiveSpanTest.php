<?php

declare(strict_types=1);

use Carbon\Carbon;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Tracing\ActiveSpan;
use Illuminate\Support\Str;

function makeActiveSpan(?string $spanId = null): array
{
    $spanId ??= (string) Str::uuid();
    GlintSpan::factory()->pending()->create(['id' => $spanId, 'name' => 'test-span']);
    $span = new ActiveSpan($spanId, Carbon::now());

    return [$span, $spanId];
}

it('spanId returns the span id', function (): void {
    [$span, $spanId] = makeActiveSpan();

    expect($span->spanId())->toBe($spanId);
});

it('tag updates metadata on the span record', function (): void {
    [$span, $spanId] = makeActiveSpan();

    $result = $span->tag('env', 'prod');

    expect($result)->toBe($span);

    $row = GlintSpan::where('id', $spanId)->first();
    expect($row->metadata['tags']['env'])->toBe('prod');
});

it('tag appends to existing tags', function (): void {
    [$span, $spanId] = makeActiveSpan();

    $span->tag('a', '1');
    $span->tag('b', '2');

    $row = GlintSpan::where('id', $spanId)->first();
    expect($row->metadata['tags'])->toBe(['a' => '1', 'b' => '2']);
});

it('tag returns self when span row does not exist', function (): void {
    $fakeId = (string) Str::uuid();
    $span = new ActiveSpan($fakeId, Carbon::now());

    $result = $span->tag('key', 'value');

    expect($result)->toBe($span);
});

it('tags writes multiple tags in one round-trip', function (): void {
    [$span, $spanId] = makeActiveSpan();

    $result = $span->tags(['region' => 'eu', 'tier' => 'free']);

    expect($result)->toBe($span);

    $row = GlintSpan::where('id', $spanId)->first();
    expect($row->metadata['tags'])->toBe(['region' => 'eu', 'tier' => 'free']);
});

it('tags merges with existing tags on span', function (): void {
    [$span, $spanId] = makeActiveSpan();

    $span->tag('first', 'a');
    $span->tags(['second' => 'b', 'third' => 'c']);

    $row = GlintSpan::where('id', $spanId)->first();
    expect($row->metadata['tags'])->toBe(['first' => 'a', 'second' => 'b', 'third' => 'c']);
});

it('tags returns self on empty array', function (): void {
    [$span] = makeActiveSpan();

    expect($span->tags([]))->toBe($span);
});

it('end updates the span status to success', function (): void {
    [$span, $spanId] = makeActiveSpan();

    $span->end();

    $row = GlintSpan::where('id', $spanId)->first();
    expect($row->status)->toBe(RecordStatus::Success)
        ->and($row->ended_at)->not->toBeNull()
        ->and($row->duration_ms)->toBeGreaterThanOrEqual(0);
});
