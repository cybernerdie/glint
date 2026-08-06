<?php

declare(strict_types=1);

namespace Prism\Prism\Structured;

final class Response
{
    public function __construct(
        public mixed $structured = null,
        public string $text = '',
        public mixed $finishReason = 'stop',
        public mixed $usage = null,
    ) {}
}
