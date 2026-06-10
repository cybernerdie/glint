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
