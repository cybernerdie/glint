<?php

declare(strict_types=1);

namespace Prism\Prism\Text;

final readonly class Request
{
    /**
     * @param  array<int, mixed>  $messages
     */
    public function __construct(
        private string $model = 'test-model',
        private array $messages = [],
        private int|float|null $temperature = null,
        private ?int $maxTokens = null,
        private int|float|null $topP = null,
    ) {}

    public function model(): string
    {
        return $this->model;
    }

    /**
     * @return array<int, mixed>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public function temperature(): int|float|null
    {
        return $this->temperature;
    }

    public function maxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function topP(): int|float|null
    {
        return $this->topP;
    }
}
