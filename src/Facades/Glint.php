<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Facades;

use Cybernerdie\Glint\GlintManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Cybernerdie\Glint\Contracts\TraceInterface trace(string $name, array<string, mixed> $metadata = [])
 * @method static \Cybernerdie\Glint\Contracts\SpanInterface span(string $name, array<string, mixed> $metadata = [])
 * @method static \Cybernerdie\Glint\Contracts\GenerationInterface generation(string $name, string $provider, string $model, array<string, mixed> $metadata = [])
 * @method static bool isEnabled()
 * @method static \Cybernerdie\Glint\Testing\GlintFake fake()
 * @method static void filter(callable $callback)
 * @method static void flushFilters()
 * @method static bool shouldRecord(\Cybernerdie\Glint\Filtering\FilterEntry $entry)
 *
 * @see GlintManager
 */
final class Glint extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'glint';
    }
}
