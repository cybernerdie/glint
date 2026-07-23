<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class VolumeBucketsQuery
{
    public function __construct(
        private readonly string $period,
        private readonly ?Carbon $fromDt,
        private readonly ?Carbon $toDt,
    ) {}

    /** @return Collection<int, array{label: string, total: int}> */
    public function get(): Collection
    {
        return $this->period === '24h'
            ? $this->hourlyBuckets()
            : $this->dailyBuckets();
    }

    /** @return Collection<int, array{label: string, total: int}> */
    private function hourlyBuckets(): Collection
    {
        $expr = $this->hourBucketExpression();

        $rows = GlintGeneration::query()
            ->selectRaw($expr.' as hour_key, COUNT(*) as total')
            ->where('started_at', '>=', now()->subHours(24))
            ->groupByRaw($expr)
            ->get();

        /** @var array<string, int> $hourlyTotals */
        $hourlyTotals = [];
        foreach ($rows as $row) {
            $key = $row->getAttribute('hour_key');
            $total = $row->getAttribute('total');
            if (is_string($key) && $key !== '') {
                $hourlyTotals[$key] = is_numeric($total) ? (int) $total : 0;
            }
        }

        $buckets = [];
        for ($h = 23; $h >= 0; $h--) {
            $hour = now()->subHours($h);
            $buckets[] = [
                'label' => $h % 4 === 0 ? $hour->format('ga') : '',
                'total' => $hourlyTotals[$hour->format('Y-m-d H')] ?? 0,
            ];
        }

        return collect($buckets);
    }

    /** @return Collection<int, array{label: string, total: int}> */
    private function dailyBuckets(): Collection
    {
        $rows = GlintGeneration::query()
            ->selectRaw('DATE(started_at) as date, COUNT(*) as total')
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->when($this->fromDt === null && $this->toDt === null, fn ($q) => $q->where('started_at', '>=', now()->subDays(13)->startOfDay()))
            ->groupByRaw('DATE(started_at)')
            ->orderBy('date')
            ->get();

        /** @var array<string, int> $dailyTotals */
        $dailyTotals = [];
        foreach ($rows as $row) {
            $date = $row->getAttribute('date');
            $total = $row->getAttribute('total');
            if (is_string($date) && $date !== '') {
                $dailyTotals[$date] = is_numeric($total) ? (int) $total : 0;
            }
        }

        $rangeEnd = ($this->toDt ?? now())->copy()->startOfDay();

        $dayCount = match ($this->period) {
            '7d'    => 7,
            '30d'   => 30,
            '90d'   => 90,
            default => $this->fromDt !== null
                ? min(120, (int) $this->fromDt->copy()->startOfDay()->diffInDays($rangeEnd) + 1)
                : 14,
        };

        $buckets = [];
        for ($i = $dayCount - 1; $i >= 0; $i--) {
            $day = $rangeEnd->copy()->subDays($i);
            $buckets[] = [
                'label' => $dayCount <= 14
                    ? $day->format('M j')
                    : ($i % 7 === 0 ? $day->format('M j') : ''),
                'total' => $dailyTotals[$day->format('Y-m-d')] ?? 0,
            ];
        }

        return collect($buckets);
    }

    /** @return literal-string */
    private function hourBucketExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT(started_at, '%Y-%m-%d %H')",
            'pgsql'            => "to_char(started_at, 'YYYY-MM-DD HH24')",
            default            => "strftime('%Y-%m-%d %H', started_at)",
        };
    }
}
