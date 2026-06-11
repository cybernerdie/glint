<?php

declare(strict_types=1);

use Cybernerdie\Glint\Console\Commands\PublishCommand;
use Illuminate\Support\Facades\File;

afterEach(function () {
    // Published views override the package views for every test that runs
    // after this file — remove them so view resolution stays on resources/views.
    File::deleteDirectory(resource_path('views/vendor/glint'));
});

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
