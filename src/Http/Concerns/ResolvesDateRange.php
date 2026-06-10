<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait ResolvesDateRange
{
    /**
     * Convert a period slug + optional custom dates into concrete Carbon bounds.
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function resolveDateRange(string $period, string $fromDate, string $toDate): array
    {
        if ($period === '' || $period === 'today') {
            return [now()->startOfDay(), now()->endOfDay()];
        }

        if ($period === 'week') {
            return [now()->startOfWeek(), now()->endOfWeek()];
        }

        if ($period === 'month') {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }

        if ($period === '3months') {
            return [now()->subMonths(3)->startOfDay(), now()->endOfDay()];
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
