<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Contracts;

interface InstrumentationDriver
{
    public function isAvailable(): bool;

    public function register(): void;
}
