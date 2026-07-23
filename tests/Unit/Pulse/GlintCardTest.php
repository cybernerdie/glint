<?php

declare(strict_types=1);

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Pulse\GlintCard;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    View::shouldReceive('make')
        ->once()
        ->with('glint::pulse.glint-card', Mockery::capture($this->viewData))
        ->andReturn(Mockery::mock(ViewContract::class));
});

it('returns zeros when there are no generations', function () {
    (new GlintCard)->render();

    expect($this->viewData['totalRequests'])->toBe(0)
        ->and($this->viewData['totalCost'])->toBe(0.0)
        ->and($this->viewData['errorRate'])->toBe(0.0)
        ->and($this->viewData['sparkline'])->toBe([]);
});

it('calculates totals from generations within the period', function () {
    GlintGeneration::factory()->count(19)->create([
        'status' => RecordStatus::Success,
        'cost_usd' => 0.175,
        'started_at' => now()->subMinutes(30),
    ]);

    GlintGeneration::factory()->create([
        'status' => RecordStatus::Error,
        'cost_usd' => 0.175,
        'started_at' => now()->subMinutes(30),
    ]);

    (new GlintCard)->render();

    expect($this->viewData['totalRequests'])->toBe(20)
        ->and($this->viewData['totalCost'])->toBe(3.5)
        ->and($this->viewData['errorRate'])->toBe(5.0);
});

it('excludes generations outside the selected period', function () {
    GlintGeneration::factory()->create([
        'status' => RecordStatus::Success,
        'cost_usd' => 5.0,
        'started_at' => now()->subHours(2),
    ]);

    (new GlintCard)->render();

    expect($this->viewData['totalRequests'])->toBe(0)
        ->and($this->viewData['totalCost'])->toBe(0.0);
});

it('returns zero error rate when there are no failed generations', function () {
    GlintGeneration::factory()->create([
        'status' => RecordStatus::Success,
        'cost_usd' => 1.0,
        'started_at' => now()->subMinutes(10),
    ]);

    (new GlintCard)->render();

    expect($this->viewData['errorRate'])->toBe(0.0);
});

it('builds sparkline from daily aggregates for 7d period', function () {
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

    $card = new GlintCard;
    $card->period = '7_days';
    $card->render();

    $sparkline = $this->viewData['sparkline'];

    expect($sparkline)->toHaveCount(2)
        ->and(array_values($sparkline))->toBe([2.5, 1.0]);
});

it('excludes sparkline data older than the selected period', function () {
    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Day,
        'period_at' => now()->subDays(10)->startOfDay(),
        'total_cost_usd' => 99.0,
        'total_requests' => 1,
        'failed_requests' => 0,
    ]);

    $card = new GlintCard;
    $card->period = '7_days';
    $card->render();

    expect($this->viewData['sparkline'])->toBe([]);
});

it('uses hourly aggregates for sparkline when period is not 7 days', function () {
    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Hour,
        'period_at' => now()->startOfHour(),
        'total_cost_usd' => 0.5,
        'total_requests' => 5,
        'failed_requests' => 0,
    ]);

    GlintAggregate::factory()->create([
        'period' => AggregatePeriod::Day,
        'period_at' => now()->startOfDay(),
        'total_cost_usd' => 50.0,
        'total_requests' => 100,
        'failed_requests' => 0,
    ]);

    (new GlintCard)->render();

    $sparkline = $this->viewData['sparkline'];

    expect($sparkline)->toHaveCount(1)
        ->and(array_values($sparkline))->toBe([0.5]);
});
