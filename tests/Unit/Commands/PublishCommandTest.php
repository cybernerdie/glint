<?php

declare(strict_types=1);

use Cybernerdie\Glint\Console\Commands\PublishCommand;

it('has the correct signature', function () {
    expect((new PublishCommand)->getName())->toBe('glint:publish');
});

it('publishes config when --config flag given', function () {
    $this->artisan('glint:publish', ['--config' => true])
        ->assertSuccessful();
});

it('publishes all assets when --all flag given', function () {
    $this->artisan('glint:publish', ['--all' => true])
        ->assertSuccessful();
});

it('publishes all assets when no specific option given', function () {
    $this->artisan('glint:publish')
        ->assertSuccessful();
});

it('outputs success message after publishing', function () {
    $this->artisan('glint:publish', ['--config' => true])
        ->expectsOutputToContain('published successfully')
        ->assertSuccessful();
});
