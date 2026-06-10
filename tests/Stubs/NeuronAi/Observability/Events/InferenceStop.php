<?php

declare(strict_types=1);

namespace NeuronAI\Observability\Events;

use NeuronAI\Chat\Messages\Message;

class InferenceStop
{
    public function __construct(
        public readonly Message|false $message,
        public readonly Message $response,
    ) {}
}
