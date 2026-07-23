<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

final class DashboardController
{
    public function index(): ViewContract
    {
        // Cache dashboard stats for 60 seconds — these aggregate queries can be
        // expensive on large tables and the dashboard is not a real-time view.
        // rescue() wraps the entire Cache::remember so a missing cache store
        // (e.g. Redis down) degrades gracefully rather than throwing.
        $stats = rescue(fn () => Cache::remember('__glint__:dashboard.stats', 60, function () {
            return rescue(function () {
                $row = GlintAggregate::query()
                    ->selectRaw('SUM(total_requests) as total, SUM(failed_requests) as errors, SUM(total_cost_usd) as total_cost, SUM(avg_duration_ms * total_requests) / NULLIF(SUM(total_requests), 0) as avg_duration')
                    ->first();

                $attrs = $row ? $row->getAttributes() : [];
                $total = isset($attrs['total']) && is_numeric($attrs['total']) ? (int) $attrs['total'] : 0;
                $errors = isset($attrs['errors']) && is_numeric($attrs['errors']) ? (int) $attrs['errors'] : 0;
                $totalCost = isset($attrs['total_cost']) && is_numeric($attrs['total_cost']) ? (float) $attrs['total_cost'] : 0.0;
                $avgDuration = isset($attrs['avg_duration']) && is_numeric($attrs['avg_duration']) ? (int) $attrs['avg_duration'] : 0;
                $errorRate = $total > 0 ? round(($errors / $total) * 100, 1) : 0.0;

                return [
                    'total_traces' => GlintTrace::query()->count(),
                    'total_generations' => $total,
                    'total_cost_usd' => $totalCost,
                    'avg_duration_ms' => $avgDuration,
                    'error_rate' => $errorRate,
                ];
            }, [
                'total_traces' => 0,
                'total_generations' => 0,
                'total_cost_usd' => 0.0,
                'avg_duration_ms' => 0,
                'error_rate' => 0.0,
            ]);
        }), [
            'total_traces' => 0,
            'total_generations' => 0,
            'total_cost_usd' => 0.0,
            'avg_duration_ms' => 0,
            'error_rate' => 0.0,
        ]);

        $recentTraces = rescue(
            fn () => GlintTrace::query()->latest('started_at')->limit(10)->get(),
            collect()
        );

        $costByProvider = rescue(
            fn () => GlintAggregate::query()
                ->selectRaw('provider, SUM(total_cost_usd) as total_cost, SUM(total_requests) as count')
                ->groupBy('provider')
                ->orderByDesc('total_cost')
                ->limit(10)
                ->get(),
            collect()
        );

        return View::make('glint::dashboard', compact('stats', 'recentTraces', 'costByProvider'));
    }
}
