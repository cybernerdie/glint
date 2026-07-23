<?php

declare(strict_types=1);

use Cybernerdie\Glint\Models\GlintTrace;

beforeEach(function () {
    config()->set('glint.enabled', true);
});

it('returns 200 on users index', function () {
    $this->get(route('glint.users.index'))
        ->assertStatus(200);
});

it('shows users when traces with user_id exist', function () {
    GlintTrace::factory()->create(['user_id' => 'alice@example.com']);

    $this->get(route('glint.users.index'))
        ->assertStatus(200)
        ->assertSee('alice@example.com');
});

it('filters users by search term', function () {
    GlintTrace::factory()->create(['user_id' => 'alice@example.com']);
    GlintTrace::factory()->create(['user_id' => 'bob@example.com']);

    $this->get(route('glint.users.index', ['search' => 'alice']))
        ->assertStatus(200)
        ->assertSee('alice@example.com');
});

it('returns 200 on users index for 7d period', function () {
    $this->get(route('glint.users.index', ['period' => '7d']))
        ->assertStatus(200);
});

it('returns 200 on users index for 30d period', function () {
    $this->get(route('glint.users.index', ['period' => '30d']))
        ->assertStatus(200);
});

it('returns 200 on users index for 90d period', function () {
    $this->get(route('glint.users.index', ['period' => '90d']))
        ->assertStatus(200);
});

it('returns 200 on users index for custom period', function () {
    $this->get(route('glint.users.index', [
        'period' => 'custom',
        'from' => '2026-01-01',
        'to' => '2026-06-30',
    ]))->assertStatus(200);
});

it('returns 200 on user show page', function () {
    GlintTrace::factory()->create(['user_id' => 'user-show-001']);

    $this->get(route('glint.users.show', 'user-show-001'))
        ->assertStatus(200);
});

it('returns 200 on user show for 7d period', function () {
    GlintTrace::factory()->create(['user_id' => 'user-7d']);

    $this->get(route('glint.users.show', ['userId' => 'user-7d', 'period' => '7d']))
        ->assertStatus(200);
});

it('returns 200 on user show for 30d period', function () {
    GlintTrace::factory()->create(['user_id' => 'user-30d']);

    $this->get(route('glint.users.show', ['userId' => 'user-30d', 'period' => '30d']))
        ->assertStatus(200);
});

it('returns 200 on user show for 90d period', function () {
    GlintTrace::factory()->create(['user_id' => 'user-90d']);

    $this->get(route('glint.users.show', ['userId' => 'user-90d', 'period' => '90d']))
        ->assertStatus(200);
});

it('returns 200 on user show for custom period', function () {
    GlintTrace::factory()->create(['user_id' => 'user-custom']);

    $this->get(route('glint.users.show', [
        'userId' => 'user-custom',
        'period' => 'custom',
        'from' => '2026-01-01',
        'to' => '2026-12-31',
    ]))->assertStatus(200);
});

it('returns 200 for user show when user has no traces', function () {
    $this->get(route('glint.users.show', 'non-existent-user-99'))
        ->assertStatus(200);
});
