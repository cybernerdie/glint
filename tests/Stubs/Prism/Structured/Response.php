<?php

declare(strict_types=1);

namespace Prism\Prism\Structured;

final class Response
{
    public function __construct(
        public mixed $structured = null,
    ) {}
}
