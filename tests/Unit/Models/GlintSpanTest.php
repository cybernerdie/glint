<?php

declare(strict_types=1);

use Cybernerdie\Glint\Models\GlintSpan;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('uses the glint_spans table', function () {
    expect((new GlintSpan)->getTable())->toBe('glint_spans');
});

it('has the MassPrunable trait', function () {
    expect(in_array(MassPrunable::class, class_uses_recursive(GlintSpan::class)))->toBeTrue();
});

it('casts metadata as array', function () {
    expect((new GlintSpan)->getCasts())->toHaveKey('metadata');
    expect((new GlintSpan)->getCasts()['metadata'])->toBe('array');
});

it('prunable query uses retention config', function () {
    config()->set('glint.retention.traces_days', 14);

    $query = GlintSpan::make()->prunable()->toSql();

    expect($query)->toContain('started_at');
});

it('trace relationship returns a BelongsTo', function () {
    $span = new GlintSpan;

    expect($span->trace())->toBeInstanceOf(BelongsTo::class);
});
