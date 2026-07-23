<?php

declare(strict_types=1);

use Cybernerdie\Glint\Console\Commands\PruneCommand;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Facades\DB;

it('has the correct signature', function () {
    expect((new PruneCommand)->getName())->toBe('glint:prune');
});

it('outputs pruned count for each table', function () {
    $this->artisan('glint:prune')
        ->expectsOutputToContain('glint_traces')
        ->expectsOutputToContain('glint_spans')
        ->expectsOutputToContain('glint_generations')
        ->expectsOutputToContain('glint_aggregates')
        ->expectsOutputToContain('glint_alert_events')
        ->assertSuccessful();
});

it('deletes prunable records', function () {
    GlintTrace::factory()->create(['started_at' => now()->subDays(60)]);

    config()->set('glint.retention.traces_days', 30);

    expect(GlintTrace::count())->toBe(1);

    $this->artisan('glint:prune')->assertSuccessful();

    expect(GlintTrace::count())->toBe(0);
});

it('does not delete records within the retention window', function () {
    GlintTrace::factory()->create(['started_at' => now()->subDays(5)]);

    config()->set('glint.retention.traces_days', 30);

    $this->artisan('glint:prune')->assertSuccessful();

    expect(GlintTrace::count())->toBe(1);
});

it('accepts a --days option and prunes records older than that value', function () {
    GlintTrace::factory()->create(['started_at' => now()->subDays(10)]);

    $this->artisan('glint:prune', ['--days' => 5])->assertSuccessful();

    expect(GlintTrace::count())->toBe(0);
});

it('returns failure when --days is below the minimum', function () {
    $this->artisan('glint:prune', ['--days' => 0])
        ->expectsOutputToContain('--days must be at least')
        ->assertFailed();
});

it('returns failure when glint.retention.traces_days is below the minimum', function () {
    config()->set('glint.retention.traces_days', 0);

    $this->artisan('glint:prune')
        ->expectsOutputToContain('glint.retention.traces_days')
        ->assertFailed();
});

it('returns failure when glint.retention.aggregates_days is below the minimum', function () {
    config()->set('glint.retention.traces_days', 30);
    config()->set('glint.retention.aggregates_days', 0);

    $this->artisan('glint:prune')
        ->expectsOutputToContain('glint.retention.aggregates_days')
        ->assertFailed();
});

it('shows a skipped warning when a model prune throws an exception', function () {
    DB::statement('DROP TABLE glint_traces');

    $this->artisan('glint:prune')
        ->expectsOutputToContain('skipped')
        ->assertSuccessful();

    DB::statement('CREATE TABLE glint_traces (id TEXT PRIMARY KEY, name TEXT NOT NULL, status TEXT NOT NULL DEFAULT \'pending\', metadata TEXT, started_at DATETIME NOT NULL, ended_at DATETIME, duration_ms INTEGER, created_at DATETIME, updated_at DATETIME)');
});
