<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Carbon;

final readonly class DashboardStatsQuery
{
    public function __construct(
        private ?Carbon $fromDt,
        private ?Carbon $toDt,
    ) {}

    /** @return array{total_traces: int, total_generations: int, total_cost_usd: float, avg_duration_ms: int, error_rate: float} */
    public function get(): array
    {
        $genQuery = GlintGeneration::query()
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt));

        $row = (clone $genQuery)
            ->selectRaw(
                'COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as errors, SUM(cost_usd) as total_cost, AVG(duration_ms) as avg_duration',
                [RecordStatus::Error->value]
            )
            ->first();

        $attrs = $row ? $row->getAttributes() : [];
        $total = isset($attrs['total']) && is_numeric($attrs['total']) ? (int) $attrs['total'] : 0;
        $errors = isset($attrs['errors']) && is_numeric($attrs['errors']) ? (int) $attrs['errors'] : 0;
        $totalCost = isset($attrs['total_cost']) && is_numeric($attrs['total_cost']) ? (float) $attrs['total_cost'] : 0.0;
        $avgDuration = isset($attrs['avg_duration']) && is_numeric($attrs['avg_duration']) ? (int) $attrs['avg_duration'] : 0;
        $errorRate = $total > 0 ? round(($errors / $total) * 100, 1) : 0.0;

        $traceCount = GlintTrace::query()
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->count();

        return [
            'total_traces' => $traceCount,
            'total_generations' => $total,
            'total_cost_usd' => $totalCost,
            'avg_duration_ms' => $avgDuration,
            'error_rate' => $errorRate,
        ];
    }

    /** @return array{total_traces: int, total_generations: int, total_cost_usd: float, avg_duration_ms: int, error_rate: float} */
    public static function empty(): array
    {
        return [
            'total_traces' => 0,
            'total_generations' => 0,
            'total_cost_usd' => 0.0,
            'avg_duration_ms' => 0,
            'error_rate' => 0.0,
        ];
    }
}
