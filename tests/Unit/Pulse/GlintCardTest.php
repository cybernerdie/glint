<?php

declare(strict_types=1);

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Pulse\GlintCard;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    View::shouldReceive('make')
        ->once()
        ->with('glint::pulse.glint-card', Mockery::capture($this->viewData))
        ->andReturn(Mockery::mock(ViewContract::class));
});

it('returns zeros when there are no aggregates for today', function () {
    (new GlintCard)->render();

    expect($this->viewData['totalRequests'])->toBe(0)
        ->and($this->viewData['totalCost'])->toBe(0.0)
        ->and($this->viewData['errorRate'])->toBe(0.0)
        ->and($this->viewData['sparkline'])->toBe([]);
});

it('calculates totals from today aggregate row', function () {
    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Day,
        'period_at' => now()->startOfDay(),
        'total_requests' => 200,
        'failed_requests' => 10,
        'total_cost_usd' => 3.50,
    ]);

    (new GlintCard)->render();

    expect($this->viewData['totalRequests'])->toBe(200)
        ->and($this->viewData['totalCost'])->toBe(3.5)
        ->and($this->viewData['errorRate'])->toBe(5.0);
});

it('aggregates multiple rows for today', function () {
    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Day,
        'period_at' => now()->startOfDay(),
        'total_requests' => 100,
        'failed_requests' => 5,
        'total_cost_usd' => 1.0,
    ]);

    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Day,
        'period_at' => now()->startOfDay(),
        'total_requests' => 100,
        'failed_requests' => 15,
        'total_cost_usd' => 2.0,
    ]);

    (new GlintCard)->render();

    expect($this->viewData['totalRequests'])->toBe(200)
        ->and($this->viewData['totalCost'])->toBe(3.0)
        ->and($this->viewData['errorRate'])->toBe(10.0);
});

it('returns zero error rate when there are no requests', function () {
    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Day,
        'period_at' => now()->startOfDay(),
        'total_requests' => 0,
        'failed_requests' => 0,
        'total_cost_usd' => 0.0,
    ]);

    (new GlintCard)->render();

    expect($this->viewData['errorRate'])->toBe(0.0);
});

it('builds sparkline from last 7 days of daily aggregates', function () {
    $today = now()->startOfDay();

    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Day,
        'period_at' => $today,
        'total_cost_usd' => 1.0,
        'total_requests' => 10,
        'failed_requests' => 0,
    ]);

    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Day,
        'period_at' => $today->copy()->subDays(3),
        'total_cost_usd' => 2.5,
        'total_requests' => 10,
        'failed_requests' => 0,
    ]);

    (new GlintCard)->render();

    $sparkline = $this->viewData['sparkline'];

    expect($sparkline)->toHaveCount(2)
        ->and(array_values($sparkline))->toBe([2.5, 1.0]);
});

it('excludes sparkline data older than 7 days', function () {
    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Day,
        'period_at' => now()->subDays(10)->startOfDay(),
        'total_cost_usd' => 99.0,
        'total_requests' => 1,
        'failed_requests' => 0,
    ]);

    (new GlintCard)->render();

    expect($this->viewData['sparkline'])->toBe([]);
});

it('ignores non-day period aggregates for sparkline', function () {
    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Week,
        'period_at' => now()->startOfDay(),
        'total_cost_usd' => 50.0,
        'total_requests' => 5,
        'failed_requests' => 0,
    ]);

    (new GlintCard)->render();

    expect($this->viewData['sparkline'])->toBe([]);
});
