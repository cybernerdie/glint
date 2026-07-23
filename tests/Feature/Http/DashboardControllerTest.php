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
    $traceId = (string) Str::uuid();

    GlintGeneration::factory()->create(['trace_id' => $traceId]);
    GlintGeneration::factory()->failed()->create(['trace_id' => $traceId]);

    $this->get(route('glint.dashboard'))
        ->assertStatus(200);
});
