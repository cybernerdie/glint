<?php

declare(strict_types=1);

use Cybernerdie\Glint\Facades\Glint;
use Cybernerdie\Glint\Filtering\FilterEntry;
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

it('facade filter uses the injected registry', function (): void {
    Glint::filter(fn (FilterEntry $entry): bool => $entry->provider !== 'openai');

    expect(Glint::shouldRecord(new FilterEntry('openai', 'gpt-4o', null, [])))->toBeFalse()
        ->and(Glint::shouldRecord(new FilterEntry('anthropic', 'claude', null, [])))->toBeTrue();
});

it('GlintManager::fake() returns a GlintFake instance', function (): void {
    $fake = GlintManager::fake();

    expect($fake)->toBeInstanceOf(GlintFake::class);
});
