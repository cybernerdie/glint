<?php

declare(strict_types=1);

use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Database\Eloquent\MassPrunable;

it('uses the glint_traces table', function () {
    expect((new GlintTrace)->getTable())->toBe('glint_traces');
});

it('has the MassPrunable trait', function () {
    expect(in_array(MassPrunable::class, class_uses_recursive(GlintTrace::class)))->toBeTrue();
});

it('declares metadata as an array cast', function () {
    expect((new GlintTrace)->getCasts())->toHaveKey('metadata');
    expect((new GlintTrace)->getCasts()['metadata'])->toBe('array');
});

it('prunable query uses retention config', function () {
    config()->set('glint.retention.traces_days', 7);

    $query = GlintTrace::make()->prunable()->toSql();

    expect($query)->toContain('started_at');
});

it('has pending scope', function () {
    $sql = GlintTrace::query()->pending()->toSql();
    expect($sql)->toContain('status');
});

it('has successful scope', function () {
    $sql = GlintTrace::query()->successful()->toSql();
    expect($sql)->toContain('status');
});

it('has failed scope', function () {
    $sql = GlintTrace::query()->failed()->toSql();
    expect($sql)->toContain('status');
});
