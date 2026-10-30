<?php

declare(strict_types=1);

namespace Laravel\Ai\Streaming\Events;

class Error
{
    public function __construct(
        public string $message,
        public string $type = 'error',
        public bool $recoverable = false,
    ) {}
}
