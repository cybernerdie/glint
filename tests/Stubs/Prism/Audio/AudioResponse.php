<?php

declare(strict_types=1);

namespace Prism\Prism\Audio;

final class AudioResponse
{
    public function __construct(
        public string $audio = '',
    ) {}
}
