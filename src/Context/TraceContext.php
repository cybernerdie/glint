<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Context;

use Closure;

/**
 * Per-request trace context (scoped binding).
 */
final class TraceContext
{
    /** @var array<int, array{traceId: string, persist: ?Closure, persisted: bool, spans: array<int, string>}> */
    private array $stack = [];

    /** @var array<string, string> generationId => traceId */
    private array $generationMap = [];

    /** @var array<string, string> generationId => auto-created traceId */
    private array $autoTraceMap = [];

    /** @var array<int, string> */
    private array $unscopedSpans = [];

    public function openTrace(string $traceId): void
    {
        $this->stack[] = ['traceId' => $traceId, 'persist' => null, 'persisted' => true, 'spans' => []];
    }

    public function openRequestTrace(string $traceId, Closure $persist): void
    {
        $this->stack[] = ['traceId' => $traceId, 'persist' => $persist, 'persisted' => false, 'spans' => []];
    }

    public function materializeTrace(?string $traceId = null): bool
    {
        $traceId ??= $this->traceId();

        foreach (array_reverse(array_keys($this->stack)) as $index) {
            if ($this->stack[$index]['traceId'] !== $traceId) {
                continue;
            }

            if ($this->stack[$index]['persisted']) {
                return true;
            }

            $persist = $this->stack[$index]['persist'];

            if ($persist === null || ! $persist()) {
                return false;
            }

            $this->stack[$index]['persisted'] = true;

            return true;
        }

        return false;
    }

    public function isTraceMaterialized(string $traceId): bool
    {
        foreach (array_reverse($this->stack) as $trace) {
            if ($trace['traceId'] === $traceId) {
                return $trace['persisted'];
            }
        }

        return false;
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
        $index = array_key_last($this->stack);

        if ($index !== null) {
            $this->stack[$index]['spans'] = $spanId === null ? [] : [$spanId];

            return;
        }

        $this->unscopedSpans = $spanId === null ? [] : [$spanId];
    }

    public function openSpan(string $spanId): void
    {
        $index = array_key_last($this->stack);

        if ($index !== null) {
            $this->stack[$index]['spans'][] = $spanId;

            return;
        }

        $this->unscopedSpans[] = $spanId;
    }

    public function closeSpan(string $spanId): void
    {
        foreach (array_reverse(array_keys($this->stack)) as $index) {
            $position = array_search($spanId, $this->stack[$index]['spans'], true);

            if ($position === false) {
                continue;
            }

            array_splice($this->stack[$index]['spans'], $position, 1);

            return;
        }

        $position = array_search($spanId, $this->unscopedSpans, true);

        if ($position !== false) {
            array_splice($this->unscopedSpans, $position, 1);
        }
    }

    public function activeSpanId(): ?string
    {
        $trace = end($this->stack);

        if ($trace === false) {
            $span = end($this->unscopedSpans);

            return $span === false ? null : $span;
        }

        $span = end($trace['spans']);

        return $span === false ? null : $span;
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

    public function closeTrace(?string $traceId = null): void
    {
        if ($traceId === null) {
            array_pop($this->stack);
        } else {
            foreach (array_reverse(array_keys($this->stack)) as $index) {
                if ($this->stack[$index]['traceId'] === $traceId) {
                    array_splice($this->stack, $index, 1);

                    break;
                }
            }
        }

        if (empty($this->stack)) {
            $this->generationMap = [];
        }
    }
}
