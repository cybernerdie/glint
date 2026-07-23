<?php

declare(strict_types=1);

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

beforeEach(function () {
    config()->set('glint.enabled', true);
    View::share('errors', new ViewErrorBag);
});

it('renders validation error messages in create view', function () {
    $bag = new ViewErrorBag;
    $bag->put('default', new MessageBag([
        'name' => ['The name field is required.'],
        'threshold' => ['The threshold field is required.'],
        'channels' => ['The channels field is required.'],
    ]));
    View::share('errors', $bag);

    $this->get(route('glint.alerts.create'))
        ->assertStatus(200)
        ->assertSee('The name field is required.')
        ->assertSee('The threshold field is required.')
        ->assertSee('The channels field is required.');
});

it('renders validation error messages in edit view', function () {
    $rule = GlintAlertRule::factory()->create();

    $bag = new ViewErrorBag;
    $bag->put('default', new MessageBag([
        'name' => ['The name field is required.'],
        'threshold' => ['The threshold field is required.'],
    ]));
    View::share('errors', $bag);

    $this->get(route('glint.alerts.edit', $rule->id))
        ->assertStatus(200)
        ->assertSee('The name field is required.')
        ->assertSee('The threshold field is required.');
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
        'channels' => ['webhook'],
        'cooldown_minutes' => 60,
    ])->assertStatus(302);

    expect(GlintAlertRule::count())->toBe($before);
});

it('creates an alert rule with slack channel and slack_webhook_url', function () {
    $this->post(route('glint.alerts.store'), [
        'name' => 'Slack Alert',
        'type' => AlertRuleType::CostThreshold->value,
        'threshold' => 10.0,
        'period' => AggregatePeriod::Day->value,
        'channels' => ['slack'],
        'slack_webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
        'cooldown_minutes' => 60,
        'enabled' => '1',
    ])->assertRedirect(route('glint.alerts.index'));

    expect(GlintAlertRule::where('name', 'Slack Alert')->where('slack_webhook_url', 'https://hooks.slack.com/services/T00/B00/xxx')->exists())->toBeTrue();
});

it('rejects store when slack channel selected without slack_webhook_url', function () {
    $before = GlintAlertRule::count();

    $this->post(route('glint.alerts.store'), [
        'name' => 'Bad Slack Alert',
        'type' => AlertRuleType::CostThreshold->value,
        'threshold' => 5.0,
        'period' => AggregatePeriod::Day->value,
        'channels' => ['slack'],
        'cooldown_minutes' => 60,
    ])->assertStatus(302);

    expect(GlintAlertRule::count())->toBe($before);
});

it('does not store slack_webhook_url when slack channel is not selected', function () {
    $this->post(route('glint.alerts.store'), [
        'name' => 'Log Only Alert',
        'type' => AlertRuleType::CostThreshold->value,
        'threshold' => 10.0,
        'period' => AggregatePeriod::Day->value,
        'channels' => ['log'],
        'slack_webhook_url' => 'https://hooks.slack.com/services/T00/B00/xxx',
        'cooldown_minutes' => 60,
        'enabled' => '1',
    ])->assertRedirect(route('glint.alerts.index'));

    expect(GlintAlertRule::where('name', 'Log Only Alert')->whereNull('slack_webhook_url')->exists())->toBeTrue();
});

it('returns 200 on alerts edit', function () {
    $rule = GlintAlertRule::factory()->create();

    $this->get(route('glint.alerts.edit', $rule->id))
        ->assertStatus(200)
        ->assertSee($rule->name);
});

it('returns 404 when editing a non-existent rule', function () {
    $this->get(route('glint.alerts.edit', 'non-existent-99999'))
        ->assertStatus(404);
});

it('updates an alert rule and redirects to index', function () {
    $rule = GlintAlertRule::factory()->create([
        'name' => 'Original Name',
        'enabled' => true,
    ]);

    $this->put(route('glint.alerts.update', $rule->id), [
        'name' => 'Updated Name',
        'type' => AlertRuleType::ErrorRate->value,
        'threshold' => 15.0,
        'period' => AggregatePeriod::Hour->value,
        'channels' => ['log'],
        'cooldown_minutes' => 120,
        'enabled' => '1',
    ])->assertRedirect(route('glint.alerts.index'));

    expect($rule->fresh()->name)->toBe('Updated Name')
        ->and((float) ($rule->fresh()->threshold_config['threshold'] ?? 0))->toBe(15.0)
        ->and($rule->fresh()->cooldown_minutes)->toBe(120);
});

it('update validates required fields', function () {
    $rule = GlintAlertRule::factory()->create(['name' => 'Unchanged']);

    $this->put(route('glint.alerts.update', $rule->id), [])
        ->assertStatus(302);

    expect($rule->fresh()->name)->toBe('Unchanged');
});

it('update requires slack_webhook_url when slack channel is selected', function () {
    $rule = GlintAlertRule::factory()->create();

    $this->put(route('glint.alerts.update', $rule->id), [
        'name' => 'Slack Rule',
        'type' => AlertRuleType::CostThreshold->value,
        'threshold' => 5.0,
        'period' => AggregatePeriod::Day->value,
        'channels' => ['slack'],
        'cooldown_minutes' => 60,
    ])->assertStatus(302);
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

it('returns 404 when destroying a non-existent rule', function () {
    $this->delete(route('glint.alerts.destroy', 'non-existent-99999'))
        ->assertStatus(404);
});
