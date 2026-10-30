<?php

declare(strict_types=1);

use Cybernerdie\Glint\Enums\AlertChannel;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('uses the glint_alert_rules table', function () {
    expect((new GlintAlertRule)->getTable())->toBe('glint_alert_rules');
});

it('casts enabled as boolean', function () {
    expect((new GlintAlertRule)->getCasts())->toHaveKey('enabled');
    expect((new GlintAlertRule)->getCasts()['enabled'])->toBe('boolean');
});

it('enabled scope filters by enabled = true', function () {
    $sql = GlintAlertRule::query()->enabled()->toSql();

    expect($sql)->toContain('enabled');
});

it('events relationship returns a HasMany', function () {
    $rule = new GlintAlertRule;

    expect($rule->events())->toBeInstanceOf(HasMany::class);
});

it('isWithinCooldown returns false when last_triggered_at is null', function () {
    $rule = new GlintAlertRule(['cooldown_minutes' => 60]);

    expect($rule->isWithinCooldown())->toBeFalse();
});

it('isWithinCooldown returns true when within cooldown window', function () {
    $rule = GlintAlertRule::factory()->withinCooldown()->create(['cooldown_minutes' => 60]);

    expect($rule->isWithinCooldown())->toBeTrue();
});

it('isWithinCooldown returns false when cooldown has elapsed', function () {
    $rule = GlintAlertRule::factory()->cooldownElapsed()->create(['cooldown_minutes' => 10]);

    expect($rule->isWithinCooldown())->toBeFalse();
});

it('owns and validates notification destinations', function (): void {
    $rule = new GlintAlertRule([
        'mail_to' => 'alerts@example.com',
        'webhook_url' => 'https://example.com/hook',
        'slack_webhook_url' => null,
    ]);

    expect($rule->notificationDestination(AlertChannel::Mail))->toBe('alerts@example.com')
        ->and($rule->hasNotificationDestination(AlertChannel::Webhook))->toBeTrue()
        ->and($rule->hasNotificationDestination(AlertChannel::Slack))->toBeFalse()
        ->and($rule->notificationDestination(AlertChannel::Log))->toBeNull();

    $rule->mail_to = '   ';

    expect($rule->hasNotificationDestination(AlertChannel::Mail))->toBeFalse();
});
