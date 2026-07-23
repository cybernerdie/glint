<?php

declare(strict_types=1);

use Carbon\Carbon;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Tracing\ActiveGeneration;
use Illuminate\Support\Str;

function makeActiveGeneration(?string $genId = null, string $provider = 'openai', string $model = 'gpt-4o'): array
{
    $genId ??= (string) Str::uuid();
    GlintGeneration::factory()->pending()->create([
        'id' => $genId,
        'name' => 'test-generation',
        'provider' => $provider,
        'model' => $model,
    ]);
    $pricing = app(PricingRegistry::class);
    $generation = new ActiveGeneration($genId, $pricing, $provider, $model, Carbon::now());

    return [$generation, $genId];
}

it('generationId returns the generation id', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    expect($generation->generationId())->toBe($genId);
});

it('tag updates metadata on the generation record', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    $result = $generation->tag('env', 'staging');

    expect($result)->toBe($generation);

    $row = GlintGeneration::where('id', $genId)->first();
    expect($row->metadata['tags']['env'])->toBe('staging');
});

it('tag appends to existing tags', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    $generation->tag('x', '1');
    $generation->tag('y', '2');

    $row = GlintGeneration::where('id', $genId)->first();
    expect($row->metadata['tags'])->toBe(['x' => '1', 'y' => '2']);
});

it('tag returns self when generation row does not exist', function (): void {
    $fakeId = (string) Str::uuid();
    $pricing = app(PricingRegistry::class);
    $generation = new ActiveGeneration($fakeId, $pricing, 'openai', 'gpt-4o', Carbon::now());

    $result = $generation->tag('key', 'value');

    expect($result)->toBe($generation);
});

it('tags writes multiple tags in one round-trip', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    $result = $generation->tags(['lang' => 'en', 'model_version' => 'v2']);

    expect($result)->toBe($generation);

    $row = GlintGeneration::where('id', $genId)->first();
    expect($row->metadata['tags'])->toBe(['lang' => 'en', 'model_version' => 'v2']);
});

it('tags merges with existing tags on generation', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    $generation->tag('first', 'a');
    $generation->tags(['second' => 'b', 'third' => 'c']);

    $row = GlintGeneration::where('id', $genId)->first();
    expect($row->metadata['tags'])->toBe(['first' => 'a', 'second' => 'b', 'third' => 'c']);
});

it('tags returns self on empty array', function (): void {
    [$generation] = makeActiveGeneration();

    expect($generation->tags([]))->toBe($generation);
});

it('finish updates generation record with token counts and status', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    $generation->finish('The answer', 100, 50, 'stop');

    $row = GlintGeneration::where('id', $genId)->first();
    expect($row->status)->toBe(RecordStatus::Success)
        ->and($row->completion)->toBe('The answer')
        ->and((int) $row->prompt_tokens)->toBe(100)
        ->and((int) $row->completion_tokens)->toBe(50)
        ->and((int) $row->total_tokens)->toBe(150)
        ->and($row->finish_reason)->toBe('stop')
        ->and($row->ended_at)->not->toBeNull()
        ->and($row->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('finish calculates cost for known models', function (): void {
    [$generation, $genId] = makeActiveGeneration(provider: 'openai', model: 'gpt-4o');

    $generation->finish('Cost test', 1_000_000, 1_000_000, 'stop');

    $row = GlintGeneration::where('id', $genId)->first();
    expect((float) $row->cost_usd)->toBeGreaterThanOrEqual(0.0);
});

it('fail updates generation record with error status', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    $generation->fail(new RuntimeException('Provider timeout'));

    $row = GlintGeneration::where('id', $genId)->first();
    expect($row->status)->toBe(RecordStatus::Error)
        ->and($row->error_message)->toBe('Provider timeout')
        ->and($row->ended_at)->not->toBeNull()
        ->and($row->duration_ms)->toBeGreaterThanOrEqual(0);
});
