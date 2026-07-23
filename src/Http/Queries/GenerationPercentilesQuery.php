<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Support\Carbon;

/**
 * @phpstan-type PercentileRow array{model: string, provider: string, count: int, p50: int, p90: int, p95: int, p99: int}
 */
final readonly class GenerationPercentilesQuery
{
    public function __construct(
        private ?Carbon $fromDt,
        private ?Carbon $toDt,
    ) {}

    /** @return list<PercentileRow> */
    public function get(): array
    {
        $rows = GlintGeneration::query()
            ->select(['model', 'provider', 'duration_ms'])
            ->whereNotNull('duration_ms')
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->limit(5000)
            ->get();

        /** @var array<string, list<int>> $grouped */
        $grouped = [];
        /** @var array<string, string> $providers */
        $providers = [];
        foreach ($rows as $row) {
            if ($row->duration_ms === null) {
                continue;
            }
            $grouped[$row->model][] = $row->duration_ms;
            $providers[$row->model] ??= $row->provider;
        }

        /** @var list<PercentileRow> $result */
        $result = [];
        foreach ($grouped as $model => $durations) {
            sort($durations);
            $result[] = [
                'model' => $model,
                'provider' => $providers[$model] ?? '',
                'count' => count($durations),
                'p50' => $this->percentile($durations, 50),
                'p90' => $this->percentile($durations, 90),
                'p95' => $this->percentile($durations, 95),
                'p99' => $this->percentile($durations, 99),
            ];
        }

        usort($result, static fn (array $a, array $b): int => $b['p95'] <=> $a['p95']);

        return $result;
    }

    /** @param list<int> $sorted */
    private function percentile(array $sorted, int $p): int
    {
        if (empty($sorted)) {
            return 0;
        }
        $index = (int) ceil(($p / 100) * count($sorted)) - 1;

        return $sorted[max(0, $index)];
    }
}
