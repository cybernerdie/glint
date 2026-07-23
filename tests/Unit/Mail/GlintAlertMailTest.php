<?php

declare(strict_types=1);

use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Mail\GlintAlertMail;
use Cybernerdie\Glint\Models\GlintAlertRule;

function makeAlertEvent(GlintAlertRule $rule, AlertRuleType $type = AlertRuleType::CostThreshold): GlintAlertTriggered
{
    return new GlintAlertTriggered(
        alertRuleId: (int) $rule->id,
        type: $type,
        threshold: 10.0,
        currentValue: 25.0,
        period: 'day',
        channel: 'mail',
        alertEventId: 1,
    );
}

it('has the correct subject', function () {
    $rule = GlintAlertRule::factory()->create(['name' => 'High Cost Alert']);

    $mail = new GlintAlertMail(makeAlertEvent($rule), $rule);

    expect($mail->envelope()->subject)->toBe('Glint Alert: High Cost Alert');
});

it('uses the alert-triggered view', function () {
    $rule = GlintAlertRule::factory()->create();

    $mail = new GlintAlertMail(makeAlertEvent($rule), $rule);

    expect($mail->content()->view)->toBe('glint::emails.alert-triggered');
});

it('passes rule name and type label to the view', function () {
    $rule = GlintAlertRule::factory()->create(['name' => 'Cost Spike']);

    $mail = new GlintAlertMail(makeAlertEvent($rule), $rule);

    $data = $mail->content()->with;

    expect($data['ruleName'])->toBe('Cost Spike')
        ->and($data['typeLabel'])->toBe('Cost Threshold');
});

it('formats cost threshold values correctly', function () {
    $rule = GlintAlertRule::factory()->create();

    $mail = new GlintAlertMail(makeAlertEvent($rule, AlertRuleType::CostThreshold), $rule);

    $data = $mail->content()->with;

    expect($data['currentValue'])->toBe('$25.0000')
        ->and($data['threshold'])->toBe('$10.0000');
});

it('formats error rate values correctly', function () {
    $rule = GlintAlertRule::factory()->create(['type' => AlertRuleType::ErrorRate]);

    $mail = new GlintAlertMail(makeAlertEvent($rule, AlertRuleType::ErrorRate), $rule);

    $data = $mail->content()->with;

    expect($data['currentValue'])->toBe('25.0%')
        ->and($data['threshold'])->toBe('10.0%');
});

it('formats latency spike values correctly', function () {
    $rule = GlintAlertRule::factory()->create(['type' => AlertRuleType::LatencySpike]);

    $mail = new GlintAlertMail(makeAlertEvent($rule, AlertRuleType::LatencySpike), $rule);

    $data = $mail->content()->with;

    expect($data['currentValue'])->toBe('25ms')
        ->and($data['threshold'])->toBe('10ms');
});

it('passes the period to the view', function () {
    $rule = GlintAlertRule::factory()->create();

    $mail = new GlintAlertMail(makeAlertEvent($rule), $rule);

    expect($mail->content()->with['period'])->toBe('day');
});
