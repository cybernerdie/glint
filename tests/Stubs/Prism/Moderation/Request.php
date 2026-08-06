<?php

declare(strict_types=1);

namespace Prism\Prism\Moderation;

final readonly class Request
{
    public function __construct(
        private string $model = 'test-model',
    ) {}

    public function model(): string
    {
        return $this->model;
    }
}
