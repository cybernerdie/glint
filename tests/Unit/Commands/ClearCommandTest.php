<?php

declare(strict_types=1);

use Cybernerdie\Glint\Console\Commands\ClearCommand;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Facades\DB;

it('has the correct signature', function () {
    expect((new ClearCommand)->getName())->toBe('glint:clear');
});

it('requires force flag or confirmation', function () {
    $this->artisan('glint:clear')
        ->expectsConfirmation('This will delete ALL Glint data. Are you sure?', 'no')
        ->expectsOutputToContain('cancelled')
        ->assertSuccessful();
});

it('aborts without deleting when confirmation is declined', function () {
    GlintTrace::factory()->create();

    $this->artisan('glint:clear')
        ->expectsConfirmation('This will delete ALL Glint data. Are you sure?', 'no')
        ->assertSuccessful();

    expect(GlintTrace::count())->toBe(1);
});

it('deletes all records from data tables when confirmed', function () {
    GlintTrace::factory()->create();
    GlintSpan::factory()->create();

    expect(GlintTrace::count())->toBe(1);
    expect(GlintSpan::count())->toBe(1);

    $this->artisan('glint:clear', ['--force' => true])
        ->expectsOutputToContain('cleared')
        ->assertSuccessful();

    expect(GlintTrace::count())->toBe(0);
    expect(GlintSpan::count())->toBe(0);
});

it('skips confirmation when --force flag given', function () {
    $this->artisan('glint:clear', ['--force' => true])
        ->assertSuccessful();
});

it('outputs a skip warning when a table delete throws an exception', function () {
    DB::statement('DROP TABLE IF EXISTS glint_alert_events');

    $this->artisan('glint:clear', ['--force' => true])
        ->expectsOutputToContain('skipped')
        ->assertSuccessful();
});
