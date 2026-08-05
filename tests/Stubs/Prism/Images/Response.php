<?php

declare(strict_types=1);

namespace Prism\Prism\Images;

final class Response
{
    public function __construct(
        public array $images = [],
    ) {}
}
