<?php

declare(strict_types=1);

beforeEach(function () {
    config()->set('glint.enabled', true);
});

it('returns json metrics', function () {
    $this->get(route('glint.api.metrics'))
        ->assertStatus(200)
        ->assertJson([
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0.0,
            'error_count' => 0,
        ]);
});
