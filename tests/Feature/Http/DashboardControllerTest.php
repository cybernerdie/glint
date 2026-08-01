<?php

declare(strict_types=1);

use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('glint.enabled', true);
});

it('returns 200 on dashboard', function () {
    $this->get(route('glint.dashboard'))
        ->assertStatus(200);
});

it('shows total traces count', function () {
    GlintTrace::factory()->create();

    $this->get(route('glint.dashboard'))
        ->assertStatus(200)
        ->assertSee('1');
});

it('calculates error rate when there are error generations', function () {
    $traceId = Str::ulid()->toString();

    GlintGeneration::factory()->create(['trace_id' => $traceId]);
    GlintGeneration::factory()->failed()->create(['trace_id' => $traceId]);

    $this->get(route('glint.dashboard'))
        ->assertStatus(200);
});

it('treats an unknown period slug as the 24h default', function () {
    $this->get(route('glint.dashboard', ['period' => '7daaaa']))
        ->assertStatus(200)
        ->assertSee('Last 24 hours')
        ->assertDontSee('7daaaa');
});

it('returns 200 for 7d period', function () {
    GlintGeneration::factory()->create(['started_at' => now()->subDay()]);

    $this->get(route('glint.dashboard', ['period' => '7d']))
        ->assertStatus(200);
});

it('returns 200 for 30d period', function () {
    $this->get(route('glint.dashboard', ['period' => '30d']))
        ->assertStatus(200);
});

it('returns 200 for 90d period', function () {
    $this->get(route('glint.dashboard', ['period' => '90d']))
        ->assertStatus(200);
});

it('returns 200 for custom period with valid dates', function () {
    $this->get(route('glint.dashboard', [
        'period' => 'custom',
        'from' => '2026-01-01',
        'to' => '2026-06-01',
    ]))->assertStatus(200);
});

it('returns 200 for custom period with empty dates', function () {
    $this->get(route('glint.dashboard', ['period' => 'custom']))
        ->assertStatus(200);
});
