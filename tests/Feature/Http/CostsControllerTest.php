<?php

declare(strict_types=1);

beforeEach(function () {
    config()->set('glint.enabled', true);
});

it('returns 200 on costs index', function () {
    $this->get(route('glint.costs.index'))
        ->assertStatus(200);
});
