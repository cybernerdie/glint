<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Contracts;

interface GlintClientInterface
{
    /** @param array<string, mixed> $metadata */
    public function trace(string $name, array $metadata = []): TraceInterface;

    /** @param array<string, mixed> $metadata */
    public function span(string $name, array $metadata = []): SpanInterface;

    /** @param array<string, mixed> $metadata */
    public function generation(string $name, string $provider, string $model, array $metadata = []): GenerationInterface;

    public function isEnabled(): bool;
}
