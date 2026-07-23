<?php

declare(strict_types=1);

use Cybernerdie\Glint\Alerts\AlertDispatcher;
use Cybernerdie\Glint\Enums\AlertEventStatus;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintAlertEvent;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->dispatcher = new AlertDispatcher;
});

it('does nothing when no active rules exist', function () {
    GlintAlertRule::factory()->disabled()->create();

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(0);
});

it('skips a rule that is within its cooldown period', function () {
    GlintAlertRule::factory()->withinCooldown()->create([
        'threshold_config' => ['threshold' => 1.0, 'period' => 'day'],
    ]);

    GlintAggregate::factory()->create(['total_cost_usd' => 999.99]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(0);
});

it('creates an alert event when threshold is crossed', function () {
    GlintAlertRule::factory()->create([
        'threshold_config' => ['threshold' => 10.0, 'period' => 'day'],
        'channels' => ['email'],
    ]);

    GlintAggregate::factory()->create(['total_cost_usd' => 15.00]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(1);

    $event = GlintAlertEvent::first();

    expect($event->channel)->toBe('email')
        ->and($event->status)->toBe(AlertEventStatus::Sent)
        ->and($event->context['type'])->toBe('cost_threshold')
        ->and((float) $event->context['threshold'])->toBe(10.0)
        ->and((float) $event->context['current_value'])->toBe(15.0);
});

it('does not create alert when value is below threshold', function () {
    GlintAlertRule::factory()->create([
        'threshold_config' => ['threshold' => 100.0, 'period' => 'day'],
    ]);

    GlintAggregate::factory()->create(['total_cost_usd' => 5.00]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(0);
});

it('updates last_triggered_at on the rule when an alert fires', function () {
    $rule = GlintAlertRule::factory()->create([
        'type' => 'token_spike',
        'threshold_config' => ['threshold' => 100.0, 'period' => 'day'],
    ]);

    GlintAggregate::factory()->create([
        'prompt_tokens' => 600,
        'completion_tokens' => 600,
    ]);

    $this->dispatcher->evaluate();

    expect($rule->fresh()->last_triggered_at)->not->toBeNull();
});

it('fires alert with default log channel when no channels configured', function () {
    GlintAlertRule::factory()->create([
        'threshold_config' => ['threshold' => 1.0, 'period' => 'day'],
        'channels' => [],
    ]);

    GlintAggregate::factory()->create(['total_cost_usd' => 5.00]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::first()->channel)->toBe('log');
});

it('fires alert with error_rate type rule', function () {
    GlintAlertRule::factory()->create([
        'type' => 'error_rate',
        'threshold_config' => ['threshold' => 10.0, 'period' => 'day'],
        'channels' => ['slack'],
    ]);

    GlintAggregate::factory()->create([
        'failed_requests' => 3,
    ]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(1);
    expect(GlintAlertEvent::first()->context['type'])->toBe('error_rate');
});

it('does nothing when evaluate is called with no active rules', function () {
    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(0);
});

it('skips rule when no threshold is configured', function () {
    GlintAlertRule::factory()->create([
        'threshold_config' => ['period' => 'day'],
    ]);

    GlintAggregate::factory()->create(['total_cost_usd' => 999.00]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(0);
});

it('rule with provider filter only matches matching provider aggregate', function () {
    GlintAlertRule::factory()->create([
        'threshold_config' => ['threshold' => 1.0, 'period' => 'day', 'provider' => 'anthropic'],
    ]);

    GlintAggregate::factory()->create([
        'provider' => 'openai',
        'total_cost_usd' => 100.00,
    ]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(0);
});

it('error_rate returns 0.0 when total_requests is zero', function () {
    GlintAlertRule::factory()->create([
        'type' => 'error_rate',
        'threshold_config' => ['threshold' => 0.1, 'period' => 'day'],
    ]);

    GlintAggregate::factory()->create([
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'total_tokens' => 0,
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_cost_usd' => 0.00,
    ]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(0);
});

it('fires latency_spike alert when avg_duration_ms exceeds threshold', function () {
    GlintAlertRule::factory()->create([
        'type' => 'latency_spike',
        'threshold_config' => ['threshold' => 500.0, 'period' => 'day'],
    ]);

    GlintAggregate::factory()->create(['avg_duration_ms' => 800]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(1);
});

it('does not fire latency_spike alert when avg_duration_ms is below threshold', function () {
    GlintAlertRule::factory()->create([
        'type' => 'latency_spike',
        'threshold_config' => ['threshold' => 1000.0, 'period' => 'day'],
    ]);

    GlintAggregate::factory()->create(['avg_duration_ms' => 200]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(0);
});

it('does not fire latency_spike alert when avg_duration_ms is null', function () {
    GlintAlertRule::factory()->create([
        'type' => 'latency_spike',
        'threshold_config' => ['threshold' => 100.0, 'period' => 'day'],
    ]);

    GlintAggregate::factory()->create(['avg_duration_ms' => null]);

    $this->dispatcher->evaluate();

    expect(GlintAlertEvent::count())->toBe(0);
});

it('dispatches GlintAlertTriggered event when threshold is crossed', function () {
    Event::fake([GlintAlertTriggered::class]);

    GlintAlertRule::factory()->create([
        'threshold_config' => ['threshold' => 10.0, 'period' => 'day'],
        'channels' => ['email'],
    ]);

    GlintAggregate::factory()->create(['total_cost_usd' => 15.00]);

    $this->dispatcher->evaluate();

    Event::assertDispatched(GlintAlertTriggered::class, function (GlintAlertTriggered $event) {
        return $event->type === AlertRuleType::CostThreshold
            && $event->threshold === 10.0
            && $event->currentValue === 15.0
            && $event->channel === 'email'
            && $event->period === 'day';
    });
});
