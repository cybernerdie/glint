<?php

declare(strict_types=1);

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

it('uses the glint_generations table', function () {
    expect((new GlintGeneration)->getTable())->toBe('glint_generations');
});

it('has the MassPrunable trait', function () {
    expect(in_array(MassPrunable::class, class_uses_recursive(GlintGeneration::class)))->toBeTrue();
});

it('casts is_streaming to boolean', function () {
    $generation = new GlintGeneration(['is_streaming' => 1]);

    expect($generation->is_streaming)->toBeBool();
});

it('casts cost_usd to a decimal', function () {
    $generation = new GlintGeneration(['cost_usd' => '0.00125000']);

    expect($generation->cost_usd)->toBeString(); // cast to decimal string
});

it('declares metadata as an array cast', function () {
    expect((new GlintGeneration)->getCasts())->toHaveKey('metadata');
    expect((new GlintGeneration)->getCasts()['metadata'])->toBe('array');
});

it('declares prompt as an array cast', function () {
    expect((new GlintGeneration)->getCasts())->toHaveKey('prompt');
    expect((new GlintGeneration)->getCasts()['prompt'])->toBe('array');
});

it('prunable query uses retention config', function () {
    config()->set('glint.retention.traces_days', 14);

    $query = GlintGeneration::make()->prunable()->toSql();

    expect($query)->toContain('started_at');
});

it('has successful scope', function () {
    $sql = GlintGeneration::query()->successful()->toSql();

    expect($sql)->toContain('status');
});

it('has failed scope', function () {
    $sql = GlintGeneration::query()->failed()->toSql();

    expect($sql)->toContain('status');
});

it('has forProvider scope', function () {
    $sql = GlintGeneration::query()->forProvider('openai')->toSql();

    expect($sql)->toContain('provider');
});

it('has pending scope', function () {
    $sql = GlintGeneration::query()->pending()->toSql();

    expect($sql)->toContain('status');
});

it('has forModel scope', function () {
    $sql = GlintGeneration::query()->forModel('gpt-4o')->toSql();

    expect($sql)->toContain('model');
});

it('trace relationship returns a BelongsTo', function () {
    $generation = new GlintGeneration;

    expect($generation->trace())->toBeInstanceOf(BelongsTo::class);
});
