<?php

declare(strict_types=1);

use Cybernerdie\Glint\Facades\Glint;
use Cybernerdie\Glint\GlintManager;
use Cybernerdie\Glint\Testing\GlintFake;

it(/**
 * @throws ReflectionException
 */ 'facade accessor returns glint', function (): void {
    $accessor = (new ReflectionMethod(Glint::class, 'getFacadeAccessor'))
        ->invoke(null);

    expect($accessor)->toBe('glint');
});

it('facade resolves to GlintManager instance', function (): void {
    $resolved = app('glint');

    expect($resolved)->toBeInstanceOf(GlintManager::class);
});

it('GlintManager::fake() returns a GlintFake instance', function (): void {
    $fake = GlintManager::fake();

    expect($fake)->toBeInstanceOf(GlintFake::class);
});
