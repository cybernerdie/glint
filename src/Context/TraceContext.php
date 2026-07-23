<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Context;

/**
 * Per-request trace context (scoped binding — re-created each request in Octane).
 */
final class TraceContext
{
    /** @var array<int, array{traceId: string}> */
    private array $stack = [];

    /** @var array<string, string> generationId => traceId */
    private array $generationMap = [];

    /** @var array<string, string> generationId => auto-created traceId */
    private array $autoTraceMap = [];

    private ?string $activeSpanId = null;

    public function openTrace(string $traceId): void
    {
        $this->stack[] = ['traceId' => $traceId];
    }

    public function traceId(): ?string
    {
        $top = end($this->stack);

        return $top !== false ? $top['traceId'] : null;
    }

    public function registerGeneration(string $generationId, string $traceId): void
    {
        $this->generationMap[$generationId] = $traceId;
    }

    public function traceIdForGeneration(string $generationId): ?string
    {
        return $this->generationMap[$generationId] ?? $this->traceId();
    }

    public function setActiveSpan(?string $spanId): void
    {
        $this->activeSpanId = $spanId;
    }

    public function activeSpanId(): ?string
    {
        return $this->activeSpanId;
    }

    public function registerAutoTrace(string $generationId, string $traceId): void
    {
        $this->autoTraceMap[$generationId] = $traceId;
    }

    public function autoTraceIdForGeneration(string $generationId): ?string
    {
        return $this->autoTraceMap[$generationId] ?? null;
    }

    public function clearAutoTrace(string $generationId): void
    {
        unset($this->autoTraceMap[$generationId]);
    }

    public function closeTrace(): void
    {
        array_pop($this->stack);

        if (empty($this->stack)) {
            $this->generationMap = [];
            $this->activeSpanId = null;
        }
    }
}
