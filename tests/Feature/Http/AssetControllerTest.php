<?php

declare(strict_types=1);

it('serves the apple touch icon as a cacheable png', function () {
    $this->get(route('glint.touch-icon'))
        ->assertStatus(200)
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'max-age=604800, public');
});
