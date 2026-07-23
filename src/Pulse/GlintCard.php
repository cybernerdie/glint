<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Pulse;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Laravel\Pulse\Livewire\Card;
use Livewire\Attributes\Lazy;

#[Lazy]
final class GlintCard extends Card
{
    public function render(): ViewContract
    {
        $since = Carbon::now()->sub($this->periodAsInterval());

        /** @var array{totalCost: float, totalRequests: int, errorRate: float, sparkline: array<string, float>} $data */
        $data = rescue(function () use ($since): array {
            $row = GlintGeneration::query()
                ->where('started_at', '>=', $since)
                ->selectRaw(
                    'COUNT(*) as total_requests, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as failed_requests, COALESCE(SUM(cost_usd), 0) as total_cost_usd',
                    [RecordStatus::Error->value]
                )
                ->first();

            $attrs = $row ? $row->getAttributes() : [];
            $totalRequests = isset($attrs['total_requests']) && is_numeric($attrs['total_requests']) ? (int) $attrs['total_requests'] : 0;
            $failedRequests = isset($attrs['failed_requests']) && is_numeric($attrs['failed_requests']) ? (int) $attrs['failed_requests'] : 0;
            $totalCost = isset($attrs['total_cost_usd']) && is_numeric($attrs['total_cost_usd']) ? (float) $attrs['total_cost_usd'] : 0.0;
            $errorRate = $totalRequests > 0 ? round($failedRequests / $totalRequests * 100, 1) : 0.0;

            $sparkline = $this->buildSparkline($since);

            return compact('totalCost', 'totalRequests', 'errorRate', 'sparkline');
        }, ['totalCost' => 0.0, 'totalRequests' => 0, 'errorRate' => 0.0, 'sparkline' => []]);

        return View::make('glint::pulse.glint-card', $data);
    }

    /** @return array<string, float> */
    private function buildSparkline(Carbon $since): array
    {
        $aggregatePeriod = $this->period === '7_days' ? AggregatePeriod::Day : AggregatePeriod::Hour;

        /** @var array<string, float> $sparkline */
        $sparkline = GlintAggregate::query()
            ->where('period', $aggregatePeriod)
            ->where('period_at', '>=', $since)
            ->selectRaw('period_at, SUM(total_cost_usd) as cost')
            ->groupBy('period_at')
            ->orderBy('period_at')
            ->pluck('cost', 'period_at')
            ->map(fn (mixed $v) => round(is_numeric($v) ? (float) $v : 0.0, 4))
            ->toArray();

        return $sparkline;
    }
}
