<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Concerns\ResolvesDateRange;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

final class AnalyticsController
{
    use ResolvesDateRange;

    public function latency(Request $request): ViewContract
    {
        $period = $request->string('period')->toString() ?: 'today';
        $fromDate = $request->string('from')->toString();
        $toDate = $request->string('to')->toString();

        [$fromDt, $toDt] = $this->resolveDateRange($period, $fromDate, $toDate);

        // Percentiles are computed PHP-side to avoid DB-specific PERCENTILE_CONT.
        $tracePercentiles = rescue(function () use ($fromDt, $toDt): array {
            $rows = GlintTrace::query()
                ->select(['name', 'duration_ms'])
                ->whereNotNull('name')
                ->whereNotNull('duration_ms')
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->limit(5000)
                ->get();

            /** @var array<string, list<int>> $grouped */
            $grouped = [];
            foreach ($rows as $row) {
                if ($row->duration_ms === null) {
                    continue;
                }
                $grouped[(string) $row->name][] = $row->duration_ms;
            }

            /** @var array<int, array{name: string, count: int, p50: int, p90: int, p95: int, p99: int}> $result */
            $result = [];
            foreach ($grouped as $name => $durations) {
                sort($durations);
                $result[] = [
                    'name' => $name,
                    'count' => count($durations),
                    'p50' => $this->percentile($durations, 50),
                    'p90' => $this->percentile($durations, 90),
                    'p95' => $this->percentile($durations, 95),
                    'p99' => $this->percentile($durations, 99),
                ];
            }

            usort($result, static fn (array $a, array $b): int => $b['p95'] <=> $a['p95']);

            return array_slice($result, 0, 10);
        }, []);

        $generationPercentiles = rescue(function () use ($fromDt, $toDt): array {
            $rows = GlintGeneration::query()
                ->select(['model', 'provider', 'duration_ms'])
                ->whereNotNull('duration_ms')
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
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

            /** @var array<int, array{model: string, provider: string, count: int, p50: int, p90: int, p95: int, p99: int}> $result */
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
        }, []);

        $traceLatency = rescue(
            fn () => GlintTrace::query()
                ->select([
                    'name',
                    DB::raw('COUNT(*) as trace_count'),
                    DB::raw('AVG(duration_ms) as avg_duration'),
                    DB::raw('MAX(duration_ms) as max_duration'),
                    DB::raw('MIN(duration_ms) as min_duration'),
                ])
                ->whereNotNull('name')
                ->whereNotNull('duration_ms')
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->groupBy('name')
                ->orderByDesc('avg_duration')
                ->limit(20)
                ->get(),
            collect()
        );

        $tokenThroughput = rescue(
            fn () => GlintGeneration::query()
                ->select([
                    'model',
                    'provider',
                    DB::raw('COUNT(*) as request_count'),
                    DB::raw('AVG(total_tokens) as avg_tokens'),
                    DB::raw('AVG(duration_ms) as avg_duration'),
                    DB::raw('CASE WHEN SUM(duration_ms) > 0 THEN SUM(total_tokens) * 1000.0 / SUM(duration_ms) ELSE 0 END as tokens_per_second'),
                ])
                ->whereNotNull('duration_ms')
                ->where('duration_ms', '>', 0)
                ->whereNotNull('total_tokens')
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->groupBy('model', 'provider')
                ->orderByDesc('tokens_per_second')
                ->get(),
            collect()
        );

        $userLatency = rescue(
            fn () => GlintTrace::query()
                ->select([
                    'user_id',
                    DB::raw('COUNT(*) as trace_count'),
                    DB::raw('AVG(duration_ms) as avg_duration'),
                    DB::raw('MAX(duration_ms) as max_duration'),
                ])
                ->whereNotNull('user_id')
                ->whereNotNull('duration_ms')
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->groupBy('user_id')
                ->orderByDesc('max_duration')
                ->limit(20)
                ->get(),
            collect()
        );

        return View::make('glint::analytics.latency', compact(
            'tracePercentiles', 'generationPercentiles',
            'traceLatency', 'tokenThroughput', 'userLatency',
            'period', 'fromDate', 'toDate'
        ));
    }

    /**
     * Compute a percentile value from a pre-sorted list of integers.
     *
     * @param  list<int>  $sorted  Already-sorted list of integer millisecond values.
     */
    private function percentile(array $sorted, int $p): int
    {
        if (empty($sorted)) {
            return 0;
        }
        $index = (int) ceil(($p / 100) * count($sorted)) - 1;

        return $sorted[max(0, $index)];
    }
}
