<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait ResolvesDateRange
{
    /**
     * Whitelist the period slug — anything absent or unknown becomes
     * the last 24 hours, so garbage input can never widen the window.
     */
    private function normalizePeriod(string $period): string
    {
        return in_array($period, ['24h', '7d', '30d', '90d', 'custom'], true) ? $period : '24h';
    }

    /**
     * Convert a period slug + optional custom dates into concrete Carbon bounds.
     *
     * Periods are rolling windows ending now ("last 24 hours", "last 7 days"),
     * matching the convention of observability tools.
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function resolveDateRange(string $period, string $fromDate, string $toDate): array
    {
        $period = $this->normalizePeriod($period);

        if ($period === '24h') {
            return [now()->subHours(24), now()];
        }

        if ($period === '7d') {
            return [now()->subDays(6)->startOfDay(), now()->endOfDay()];
        }

        if ($period === '30d') {
            return [now()->subDays(29)->startOfDay(), now()->endOfDay()];
        }

        if ($period === '90d') {
            return [now()->subDays(89)->startOfDay(), now()->endOfDay()];
        }

        if ($period === 'custom') {
            $from = $fromDate !== '' ? Carbon::parse($fromDate)->startOfDay() : null;
            $to = $toDate !== '' ? Carbon::parse($toDate)->endOfDay() : null;

            return [$from, $to];
        }

        return [null, null];
    }

    /**
     * Apply from/to Carbon bounds to an Eloquent builder on a given column.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function applyDateRange(
        Builder $query,
        ?Carbon $from,
        ?Carbon $to,
        string $column = 'started_at',
    ): Builder {
        if ($from !== null) {
            $query->where($column, '>=', $from);
        }

        if ($to !== null) {
            $query->where($column, '<=', $to);
        }

        return $query;
    }
}
