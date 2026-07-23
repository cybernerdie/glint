<?php

declare(strict_types=1);

use Cybernerdie\Glint\Models\GlintTrace;

beforeEach(function () {
    config()->set('glint.enabled', true);
});

it('returns 200 on traces index', function () {
    $this->get(route('glint.traces.index'))
        ->assertStatus(200);
});

it('returns 404 for unknown trace', function () {
    $this->get(route('glint.traces.show', 'non-existent-trace-id'))
        ->assertStatus(404);
});

it('shows trace detail', function () {
    $trace = GlintTrace::factory()->create(['name' => 'My Test Trace']);

    $this->get(route('glint.traces.show', $trace->id))
        ->assertStatus(200)
        ->assertSee('My Test Trace');
});

it('filters traces by search term', function () {
    GlintTrace::factory()->create(['name' => 'Alpha Trace']);
    GlintTrace::factory()->create(['name' => 'Beta Trace']);

    $this->get(route('glint.traces.index', ['search' => 'Alpha']))
        ->assertStatus(200)
        ->assertSee('Alpha Trace');
});

it('filters traces by status', function () {
    GlintTrace::factory()->create(['name' => 'Success Trace']);
    GlintTrace::factory()->failed()->create(['name' => 'Error Trace']);

    $this->get(route('glint.traces.index', ['status' => 'success']))
        ->assertStatus(200);
});

it('filters traces by user_id', function () {
    GlintTrace::factory()->create(['user_id' => 'user-filter-001', 'name' => 'User Trace']);

    $this->get(route('glint.traces.index', ['user_id' => 'user-filter-001']))
        ->assertStatus(200);
});

it('returns 200 for 7d period', function () {
    $this->get(route('glint.traces.index', ['period' => '7d']))
        ->assertStatus(200);
});

it('returns 200 for 30d period', function () {
    $this->get(route('glint.traces.index', ['period' => '30d']))
        ->assertStatus(200);
});

it('returns 200 for custom period', function () {
    $this->get(route('glint.traces.index', [
        'period' => 'custom',
        'from' => '2026-01-01',
        'to' => '2026-06-01',
    ]))->assertStatus(200);
});
