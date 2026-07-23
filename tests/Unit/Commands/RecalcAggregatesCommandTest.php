<?php

declare(strict_types=1);

use Cybernerdie\Glint\Console\Commands\RecalcAggregatesCommand;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;

it('has the correct signature', function () {
    expect((new RecalcAggregatesCommand)->getName())->toBe('glint:recalc-aggregates');
});

it('creates aggregate records from generation data', function () {
    GlintGeneration::factory()->create([
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'status' => 'success',
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
        'total_tokens' => 150,
        'cost_usd' => 0.00025,
    ]);

    $this->artisan('glint:recalc-aggregates', ['--period' => 'daily'])
        ->assertSuccessful();

    expect(GlintAggregate::count())->toBe(1);

    $agg = GlintAggregate::first();
    expect($agg->provider)->toBe('openai')
        ->and($agg->model)->toBe('gpt-4o')
        ->and($agg->total_requests)->toBe(1)
        ->and((int) $agg->prompt_tokens)->toBe(100)
        ->and((int) $agg->completion_tokens)->toBe(50);
});

it('respects --from and --to date filters', function () {
    GlintGeneration::factory()->create([
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'prompt_tokens' => 200,
        'completion_tokens' => 100,
        'total_tokens' => 300,
        'cost_usd' => 0.001,
        'started_at' => now()->subDays(10),
    ]);

    GlintGeneration::factory()->create([
        'provider' => 'anthropic',
        'model' => 'claude-3-5-sonnet-20241022',
        'prompt_tokens' => 50,
        'completion_tokens' => 25,
        'total_tokens' => 75,
        'cost_usd' => 0.0005,
        'started_at' => now(),
    ]);

    $from = now()->subDays(1)->format('Y-m-d');
    $to = now()->addDay()->format('Y-m-d');

    $this->artisan('glint:recalc-aggregates', [
        '--period' => 'daily',
        '--from' => $from,
        '--to' => $to,
    ])->assertSuccessful();

    expect(GlintAggregate::where('provider', 'anthropic')->count())->toBe(1)
        ->and(GlintAggregate::where('provider', 'openai')->count())->toBe(0);
});

it('returns failure for an invalid period option', function () {
    $this->artisan('glint:recalc-aggregates', ['--period' => 'invalid'])
        ->assertFailed();
});

it('fails when --from date is invalid', function () {
    $this->artisan('glint:recalc-aggregates', ['--period' => 'daily', '--from' => 'not-a-date'])
        ->assertFailed();
});

it('fails when --to date is invalid', function () {
    $this->artisan('glint:recalc-aggregates', ['--period' => 'daily', '--to' => 'not-a-date'])
        ->assertFailed();
});

it('fails when --from is after --to', function () {
    $from = now()->addDays(5)->format('Y-m-d');
    $to = now()->subDays(5)->format('Y-m-d');

    $this->artisan('glint:recalc-aggregates', ['--period' => 'daily', '--from' => $from, '--to' => $to])
        ->assertFailed();
});

it('warns and succeeds when no generation records exist in window', function () {
    $from = now()->addDays(100)->format('Y-m-d');
    $to = now()->addDays(101)->format('Y-m-d');

    $this->artisan('glint:recalc-aggregates', ['--period' => 'daily', '--from' => $from, '--to' => $to])
        ->assertSuccessful();
});

it('aggregates with weekly period bucket', function () {
    GlintGeneration::factory()->create([
        'prompt_tokens' => 50,
        'completion_tokens' => 25,
        'total_tokens' => 75,
        'cost_usd' => 0.0001,
    ]);

    $this->artisan('glint:recalc-aggregates', ['--period' => 'weekly'])
        ->assertSuccessful();

    expect(GlintAggregate::count())->toBeGreaterThanOrEqual(1);
});

it('aggregates errors separately from successes', function () {
    GlintGeneration::factory()->failed()->create([
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'prompt_tokens' => 10,
        'completion_tokens' => 0,
        'total_tokens' => 10,
        'cost_usd' => 0,
    ]);

    $this->artisan('glint:recalc-aggregates', ['--period' => 'daily'])
        ->assertSuccessful();

    $agg = GlintAggregate::where('provider', 'openai')->first();
    expect($agg)->not->toBeNull()
        ->and($agg->failed_requests)->toBe(1)
        ->and($agg->successful_requests)->toBe(0);
});
