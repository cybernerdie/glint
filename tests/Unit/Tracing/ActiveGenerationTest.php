<?php

declare(strict_types=1);

use Carbon\Carbon;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Pricing\PricingRegistry;
use Cybernerdie\Glint\Tracing\ActiveGeneration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function makeActiveGeneration(?string $genId = null, string $provider = 'openai', string $model = 'gpt-4o'): array
{
    $genId ??= Str::uuid()->toString();
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

it('redacts generation tag values', function (): void {
    config()->set('glint.privacy.redact_patterns', ['/secret-token-\w+/']);
    [$generation, $genId] = makeActiveGeneration();

    $generation->tag('token', 'secret-token-abc123');

    $row = GlintGeneration::where('id', $genId)->first();

    expect($row->metadata['tags']['token'])->toBe('[REDACTED]');
});

it('tag appends to existing tags', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    $generation->tag('x', '1');
    $generation->tag('y', '2');

    $row = GlintGeneration::where('id', $genId)->first();
    expect($row->metadata['tags'])->toBe(['x' => '1', 'y' => '2']);
});

it('tag returns self when generation row does not exist', function (): void {
    $fakeId = Str::uuid()->toString();
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

it('prompt updates the generation prompt', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    $result = $generation->prompt('What is Laravel Glint?');

    expect($result)->toBe($generation);

    $row = GlintGeneration::where('id', $genId)->first();
    expect($row->prompt)->toBe([
        [
            'role' => 'user',
            'content' => 'What is Laravel Glint?',
        ],
    ]);
});

it('redacts manually recorded prompts', function (): void {
    config()->set('glint.privacy.redact_patterns', ['/secret-token-\w+/']);
    [$generation, $genId] = makeActiveGeneration();

    $generation->prompt('Use secret-token-abc123');

    expect(GlintGeneration::where('id', $genId)->first()->prompt)->toBe([
        [
            'role' => 'user',
            'content' => 'Use [REDACTED]',
        ],
    ]);
});

it('options updates manually recorded generation options', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    $result = $generation->options(
        temperature: 0.7,
        maxTokens: 1024,
        topP: 0.95,
        streaming: true,
    );

    expect($result)->toBe($generation);

    $row = GlintGeneration::where('id', $genId)->first();
    expect((float) $row->temperature)->toBe(0.7)
        ->and((int) $row->max_tokens)->toBe(1024)
        ->and((float) $row->top_p)->toBe(0.95)
        ->and($row->is_streaming)->toBeTrue();
});

it('options only updates values that were provided', function (): void {
    [$generation, $genId] = makeActiveGeneration();

    $generation->options(maxTokens: 2048);

    $row = GlintGeneration::where('id', $genId)->first();
    expect($row->temperature)->toBeNull()
        ->and((int) $row->max_tokens)->toBe(2048)
        ->and($row->top_p)->toBeNull()
        ->and($row->is_streaming)->toBeFalse();
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

it('finish updates aggregate buckets for manual generations', function (): void {
    [$generation] = makeActiveGeneration(provider: 'openai', model: 'gpt-4o');

    $generation->finish('The answer', 100, 50, 'stop');

    $periods = DB::table('glint_aggregates')
        ->where('provider', 'openai')
        ->where('model', 'gpt-4o')
        ->pluck('period')
        ->sort()
        ->values()
        ->toArray();

    expect($periods)->toBe(['day', 'hour', 'month', 'week']);

    $hour = DB::table('glint_aggregates')
        ->where('provider', 'openai')
        ->where('model', 'gpt-4o')
        ->where('period', 'hour')
        ->first();

    expect($hour)->not->toBeNull()
        ->and((int) $hour->total_requests)->toBe(1)
        ->and((int) $hour->successful_requests)->toBe(1)
        ->and((int) $hour->failed_requests)->toBe(0)
        ->and((int) $hour->prompt_tokens)->toBe(100)
        ->and((int) $hour->completion_tokens)->toBe(50)
        ->and((int) $hour->total_tokens)->toBe(150)
        ->and($hour->user_id)->toBe(GlintAggregate::GlobalDimension)
        ->and($hour->team_id)->toBe(GlintAggregate::GlobalDimension);
});

it('finish only increments manual aggregates once', function (): void {
    [$generation] = makeActiveGeneration(provider: 'openai', model: 'gpt-4o-mini');

    $generation->finish('First answer', 100, 50, 'stop');
    $generation->finish('Second answer', 200, 75, 'stop');

    $hour = DB::table('glint_aggregates')
        ->where('provider', 'openai')
        ->where('model', 'gpt-4o-mini')
        ->where('period', 'hour')
        ->first();

    expect($hour)->not->toBeNull()
        ->and((int) $hour->total_requests)->toBe(1)
        ->and((int) $hour->successful_requests)->toBe(1)
        ->and((int) $hour->prompt_tokens)->toBe(100)
        ->and((int) $hour->completion_tokens)->toBe(50);
});

it('redacts completion when manually finishing a generation', function (): void {
    config()->set('glint.privacy.redact_patterns', ['/secret-token-\w+/']);
    [$generation, $genId] = makeActiveGeneration();

    $generation->finish('The answer secret-token-abc123', 100, 50, 'stop');

    expect(GlintGeneration::where('id', $genId)->first()->completion)->toBe('The answer [REDACTED]');
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

it('fail updates aggregate buckets for manual generations', function (): void {
    [$generation] = makeActiveGeneration(provider: 'anthropic', model: 'claude-3-5-haiku-20241022');

    $generation->fail(new RuntimeException('Provider timeout'));

    $periods = DB::table('glint_aggregates')
        ->where('provider', 'anthropic')
        ->where('model', 'claude-3-5-haiku-20241022')
        ->pluck('period')
        ->sort()
        ->values()
        ->toArray();

    expect($periods)->toBe(['day', 'hour', 'month', 'week']);

    $hour = DB::table('glint_aggregates')
        ->where('provider', 'anthropic')
        ->where('model', 'claude-3-5-haiku-20241022')
        ->where('period', 'hour')
        ->first();

    expect($hour)->not->toBeNull()
        ->and((int) $hour->total_requests)->toBe(1)
        ->and((int) $hour->successful_requests)->toBe(0)
        ->and((int) $hour->failed_requests)->toBe(1)
        ->and((int) $hour->total_tokens)->toBe(0);
});

it('redacts error messages when manually failing a generation', function (): void {
    config()->set('glint.privacy.redact_patterns', ['/secret-token-\w+/']);
    [$generation, $genId] = makeActiveGeneration();

    $generation->fail(new RuntimeException('Provider leaked secret-token-abc123'));

    expect(GlintGeneration::where('id', $genId)->first()->error_message)->toBe('Provider leaked [REDACTED]');
});
