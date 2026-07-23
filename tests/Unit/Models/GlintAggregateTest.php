<?php

declare(strict_types=1);

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Models\GlintAggregate;
use Illuminate\Database\Eloquent\MassPrunable;

it('uses the glint_aggregates table', function () {
    expect((new GlintAggregate)->getTable())->toBe('glint_aggregates');
});

it('has the MassPrunable trait', function () {
    expect(in_array(MassPrunable::class, class_uses_recursive(GlintAggregate::class)))->toBeTrue();
});

it('prunable query uses retention config', function () {
    config()->set('glint.retention.aggregates_days', 90);

    $query = GlintAggregate::make()->prunable()->toSql();

    expect($query)->toContain('period_at');
});

it('forPeriod scope filters by period', function () {
    $sql = GlintAggregate::query()->forPeriod(AggregatePeriod::Day)->toSql();

    expect($sql)->toContain('period');
});

it('forProvider scope filters by provider', function () {
    $sql = GlintAggregate::query()->forProvider('openai')->toSql();

    expect($sql)->toContain('provider');
});
