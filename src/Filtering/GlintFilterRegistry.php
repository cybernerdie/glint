<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Filtering;

/**
 * Singleton registry for recording-filter callbacks.
 * Filter state lives here so it survives across requests without leaking per-request state.
 */
final class GlintFilterRegistry
{
    /** @var array<int, callable(FilterEntry): bool> */
    private array $callbacks = [];

    /** @param callable(FilterEntry): bool $callback */
    public function push(callable $callback): void
    {
        $this->callbacks[] = $callback;
    }

    public function shouldRecord(FilterEntry $entry): bool
    {
        foreach ($this->callbacks as $callback) {
            if (! $callback($entry)) {
                return false;
            }
        }

        return true;
    }

    public function flush(): void
    {
        $this->callbacks = [];
    }
}
