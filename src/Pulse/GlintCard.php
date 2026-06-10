<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Pulse;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Models\GlintAggregate;
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
        /** @var array{totalCost: float, totalRequests: int, errorRate: float, sparkline: array<string, float>} $data */
        $data = rescue(function (): array {
            $today = Carbon::today();

            $todayRow = GlintAggregate::query()
                ->where('period', AggregatePeriod::Day)
                ->whereDate('period_at', $today)
                ->selectRaw('COALESCE(SUM(total_requests), 0) as total_requests, COALESCE(SUM(failed_requests), 0) as failed_requests, COALESCE(SUM(total_cost_usd), 0) as total_cost_usd')
                ->first();

            $attrs = $todayRow ? $todayRow->getAttributes() : [];

            $totalRequests = isset($attrs['total_requests']) && is_numeric($attrs['total_requests']) ? (int) $attrs['total_requests'] : 0;
            $failedRequests = isset($attrs['failed_requests']) && is_numeric($attrs['failed_requests']) ? (int) $attrs['failed_requests'] : 0;
            $totalCost = isset($attrs['total_cost_usd']) && is_numeric($attrs['total_cost_usd']) ? (float) $attrs['total_cost_usd'] : 0.0;
            $errorRate = $totalRequests > 0 ? round($failedRequests / $totalRequests * 100, 1) : 0.0;

            /** @var array<string, float> $sparkline */
            $sparkline = GlintAggregate::query()
                ->where('period', AggregatePeriod::Day)
                ->where('period_at', '>=', Carbon::now()->subDays(6)->startOfDay())
                ->selectRaw('DATE(period_at) as day, SUM(total_cost_usd) as cost')
                ->groupByRaw('DATE(period_at)')
                ->orderBy('day')
                ->pluck('cost', 'day')
                ->map(fn (mixed $v) => round(is_numeric($v) ? (float) $v : 0.0, 4))
                ->toArray();

            return compact('totalCost', 'totalRequests', 'errorRate', 'sparkline');
        }, ['totalCost' => 0.0, 'totalRequests' => 0, 'errorRate' => 0.0, 'sparkline' => []]);

        return View::make('glint::pulse.glint-card', $data);
    }
}
