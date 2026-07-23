<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Context;

/**
 * Per-request trace context (scoped binding — re-created each request in Octane).
 *
 * Maintains a stack of active traces to correctly handle nested trace contexts,
 * e.g. when GlintMiddleware opens a trace and then user code opens a child trace
 * via Glint::trace(). The innermost trace is always at the top of the stack.
 */
final class TraceContext
{
    /**
     * Stack of open traces. Each entry holds the traceId and sampled flag.
     * Innermost (most recent) trace is at the end of the array.
     *
     * @var array<int, array{traceId: string, sampled: bool}>
     */
    private array $stack = [];

    /** @var array<string, string> generationId => traceId */
    private array $generationMap = [];

    private ?string $activeSpanId = null;

    public function openTrace(string $traceId, bool $sampled): void
    {
        $this->stack[] = ['traceId' => $traceId, 'sampled' => $sampled];
    }

    public function traceId(): ?string
    {
        $top = end($this->stack);

        return $top !== false ? $top['traceId'] : null;
    }

    /**
     * Whether the current (innermost) trace context is being recorded.
     *
     * Returns true when no trace is open so that LLM calls fired from
     * background jobs / Artisan commands (outside of any explicit trace) are
     * still captured by the auto-instrumentation drivers.
     */
    public function isSampled(): bool
    {
        $top = end($this->stack);

        return $top !== false ? $top['sampled'] : true;
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

    /**
     * Close the innermost trace, popping it off the stack.
     * If a parent trace was open before this one, it becomes active again.
     */
    public function closeTrace(): void
    {
        array_pop($this->stack);

        // Only clear generation map and active span when the outermost trace closes.
        if (empty($this->stack)) {
            $this->generationMap = [];
            $this->activeSpanId = null;
        }
    }
}
