<?php

declare(strict_types=1);

namespace Prism\Prism\Audio;

final class TextResponse
{
    public function __construct(
        public string $text = '',
    ) {}
}
