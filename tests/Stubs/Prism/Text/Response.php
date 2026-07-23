<?php

declare(strict_types=1);

namespace Prism\Prism\Text;

final class Response
{
    public function __construct(
        public string $text = '',
        public ?object $usage = null,
        public mixed $finishReason = 'stop',
    ) {}
}
