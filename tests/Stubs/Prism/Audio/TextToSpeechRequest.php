<?php

declare(strict_types=1);

namespace Prism\Prism\Audio;

final readonly class TextToSpeechRequest
{
    public function __construct(
        private string $model = 'test-model',
    ) {}

    public function model(): string
    {
        return $this->model;
    }
}
