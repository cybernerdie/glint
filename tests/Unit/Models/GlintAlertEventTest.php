<?php

declare(strict_types=1);

use Cybernerdie\Glint\Models\GlintAlertEvent;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('uses the glint_alert_events table', function () {
    expect((new GlintAlertEvent)->getTable())->toBe('glint_alert_events');
});

it('has the MassPrunable trait', function () {
    expect(in_array(MassPrunable::class, class_uses_recursive(GlintAlertEvent::class)))->toBeTrue();
});

it('casts context as array', function () {
    expect((new GlintAlertEvent)->getCasts())->toHaveKey('context');
    expect((new GlintAlertEvent)->getCasts()['context'])->toBe('array');
});

it('prunable query uses retention config', function () {
    config()->set('glint.retention.alert_days', 45);

    $query = GlintAlertEvent::make()->prunable()->toSql();

    expect($query)->toContain('triggered_at');
});

it('rule relationship returns a BelongsTo', function () {
    $event = new GlintAlertEvent;

    expect($event->rule())->toBeInstanceOf(BelongsTo::class);
});
