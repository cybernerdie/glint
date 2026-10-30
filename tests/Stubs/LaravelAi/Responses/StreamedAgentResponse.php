<?php

declare(strict_types=1);

namespace Laravel\Ai\Responses;

use Laravel\Ai\Responses\Data\Meta;

class StreamedAgentResponse
{
    public function __construct(public Meta $meta) {}
}
