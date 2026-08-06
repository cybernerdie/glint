<?php

declare(strict_types=1);

namespace Prism\Prism\Moderation;

final class Response
{
    public function __construct(
        public bool $flagged = false,
    ) {}
}
