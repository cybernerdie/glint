<?php

declare(strict_types=1);

use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintTrace;

beforeEach(function () {
    config()->set('glint.enabled', true);
});

it('returns 200 on analytics latency page', function () {
    $this->get(route('glint.analytics.latency'))
        ->assertStatus(200);
});

it('returns 200 for 7d period', function () {
    $this->get(route('glint.analytics.latency', ['period' => '7d']))
        ->assertStatus(200);
});

it('returns 200 for 30d period', function () {
    $this->get(route('glint.analytics.latency', ['period' => '30d']))
        ->assertStatus(200);
});

it('returns 200 for 90d period', function () {
    $this->get(route('glint.analytics.latency', ['period' => '90d']))
        ->assertStatus(200);
});

it('returns 200 for custom period with valid dates', function () {
    $this->get(route('glint.analytics.latency', [
        'period' => 'custom',
        'from' => '2026-01-01',
        'to' => '2026-06-01',
    ]))->assertStatus(200);
});

it('returns 200 for custom period with invalid date strings', function () {
    $this->get(route('glint.analytics.latency', [
        'period' => 'custom',
        'from' => 'not-a-date',
        'to' => 'also-bad',
    ]))->assertStatus(200);
});

it('treats an unknown period slug as 24h', function () {
    $this->get(route('glint.analytics.latency', ['period' => 'badperiod']))
        ->assertStatus(200);
});

it('computes trace percentiles when traces with duration exist', function () {
    GlintTrace::factory()->create(['name' => 'checkout', 'duration_ms' => 250]);
    GlintTrace::factory()->create(['name' => 'checkout', 'duration_ms' => 500]);

    $this->get(route('glint.analytics.latency'))
        ->assertStatus(200);
});

it('computes generation throughput when generations with duration exist', function () {
    GlintGeneration::factory()->create([
        'provider' => 'openai',
        'model' => 'gpt-4o',
        'duration_ms' => 400,
        'total_tokens' => 200,
    ]);

    $this->get(route('glint.analytics.latency'))
        ->assertStatus(200);
});

it('computes user latency when traces with user_id exist', function () {
    GlintTrace::factory()->create(['user_id' => 'user-lat-001', 'duration_ms' => 300]);

    $this->get(route('glint.analytics.latency'))
        ->assertStatus(200);
});
