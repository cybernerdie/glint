<?php

declare(strict_types=1);

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\AlertRuleScope;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    config()->set('glint.enabled', true);
    View::share('errors', new ViewErrorBag());
});

it('returns 200 on alerts index', function () {
    $this->get(route('glint.alerts.index'))
        ->assertStatus(200);
});

it('shows existing alert rules on index', function () {
    GlintAlertRule::factory()->create(['name' => 'My Cost Alert']);

    $this->get(route('glint.alerts.index'))
        ->assertStatus(200)
        ->assertSee('My Cost Alert');
});

it('returns 200 on alerts create', function () {
    $this->get(route('glint.alerts.create'))
        ->assertStatus(200);
});

it('creates an alert rule and redirects to index', function () {
    $this->post(route('glint.alerts.store'), [
        'name' => 'High Cost Alert',
        'type' => AlertRuleType::CostThreshold->value,
        'threshold' => 10.0,
        'period' => AggregatePeriod::Day->value,
        'scope' => AlertRuleScope::Global->value,
        'scope_id' => null,
        'provider' => null,
        'channels' => ['log'],
        'cooldown_minutes' => 60,
        'enabled' => '1',
    ])->assertRedirect(route('glint.alerts.index'));

    expect(GlintAlertRule::where('name', 'High Cost Alert')->exists())->toBeTrue();
});

it('rejects store when required fields are missing', function () {
    $before = GlintAlertRule::count();

    $this->post(route('glint.alerts.store'), [])
        ->assertStatus(302);

    expect(GlintAlertRule::count())->toBe($before);
});

it('creates an alert rule with mail channel and mail_to', function () {
    $this->post(route('glint.alerts.store'), [
        'name' => 'Mail Alert',
        'type' => AlertRuleType::ErrorRate->value,
        'threshold' => 5.0,
        'period' => AggregatePeriod::Hour->value,
        'scope' => AlertRuleScope::Global->value,
        'channels' => ['mail'],
        'mail_to' => 'admin@example.com',
        'cooldown_minutes' => 30,
        'enabled' => '1',
    ])->assertRedirect(route('glint.alerts.index'));

    expect(GlintAlertRule::where('name', 'Mail Alert')->where('mail_to', 'admin@example.com')->exists())->toBeTrue();
});

it('rejects store when mail channel selected without mail_to', function () {
    $before = GlintAlertRule::count();

    $this->post(route('glint.alerts.store'), [
        'name' => 'Bad Mail Alert',
        'type' => AlertRuleType::CostThreshold->value,
        'threshold' => 5.0,
        'period' => AggregatePeriod::Day->value,
        'scope' => AlertRuleScope::Global->value,
        'channels' => ['mail'],
        'cooldown_minutes' => 60,
    ])->assertStatus(302);

    expect(GlintAlertRule::count())->toBe($before);
});

it('rejects store when webhook channel selected without webhook_url', function () {
    $before = GlintAlertRule::count();

    $this->post(route('glint.alerts.store'), [
        'name' => 'Bad Webhook Alert',
        'type' => AlertRuleType::CostThreshold->value,
        'threshold' => 5.0,
        'period' => AggregatePeriod::Day->value,
        'scope' => AlertRuleScope::Global->value,
        'channels' => ['webhook'],
        'cooldown_minutes' => 60,
    ])->assertStatus(302);

    expect(GlintAlertRule::count())->toBe($before);
});

it('toggles alert rule from enabled to disabled', function () {
    $rule = GlintAlertRule::factory()->create(['enabled' => true]);

    $this->post(route('glint.alerts.toggle', $rule->id))
        ->assertRedirect(route('glint.alerts.index'));

    expect((bool) $rule->fresh()->enabled)->toBeFalse();
});

it('toggles alert rule from disabled to enabled', function () {
    $rule = GlintAlertRule::factory()->create(['enabled' => false]);

    $this->post(route('glint.alerts.toggle', $rule->id))
        ->assertRedirect(route('glint.alerts.index'));

    expect((bool) $rule->fresh()->enabled)->toBeTrue();
});

it('returns 404 when toggling a non-existent rule', function () {
    $this->post(route('glint.alerts.toggle', 'non-existent-99999'))
        ->assertStatus(404);
});

it('deletes an alert rule and redirects', function () {
    $rule = GlintAlertRule::factory()->create();

    $this->delete(route('glint.alerts.destroy', $rule->id))
        ->assertRedirect(route('glint.alerts.index'));

    expect(GlintAlertRule::find($rule->id))->toBeNull();
});

it('destroy redirects to index even when rule does not exist', function () {
    $this->delete(route('glint.alerts.destroy', 'non-existent-99999'))
        ->assertRedirect(route('glint.alerts.index'));
});
