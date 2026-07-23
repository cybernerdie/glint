<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Filtering;

/**
 * Singleton registry for recording-filter callbacks.
 *
 * Filters are typically registered in a ServiceProvider::boot() callback via
 * GlintManager::filter(). Because GlintManager is scoped (re-created per
 * request in Octane), filter state cannot live on the manager instance.
 * Storing it here — in a singleton — ensures filters registered at boot
 * survive across every request without leaking per-request state.
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
