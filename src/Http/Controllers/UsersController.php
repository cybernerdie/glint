<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Concerns\ResolvesDateRange;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

final class UsersController
{
    use ResolvesDateRange;

    public function index(Request $request): ViewContract
    {
        $search   = $request->string('search')->toString();
        $period   = $request->string('period')->toString() ?: 'today';
        $fromDate = $request->string('from')->toString();
        $toDate   = $request->string('to')->toString();

        [$fromDt, $toDt] = $this->resolveDateRange($period, $fromDate, $toDate);

        $users = rescue(function () use ($search, $fromDt, $toDt) {
            return GlintTrace::query()
                ->select([
                    'glint_traces.user_id',
                    DB::raw('COUNT(DISTINCT glint_traces.id) as trace_count'),
                    DB::raw('SUM(glint_generations.cost_usd) as total_cost'),
                    DB::raw('AVG(glint_traces.duration_ms) as avg_duration'),
                    DB::raw('MAX(glint_traces.started_at) as last_seen'),
                    DB::raw('SUM(glint_generations.total_tokens) as total_tokens'),
                ])
                ->leftJoin('glint_generations', 'glint_generations.trace_id', '=', 'glint_traces.id')
                ->whereNotNull('glint_traces.user_id')
                ->when($search !== '', fn ($q) => $q->where('glint_traces.user_id', 'like', '%'.$search.'%'))
                ->when($fromDt, fn ($q) => $q->where('glint_traces.started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('glint_traces.started_at', '<=', $toDt))
                ->groupBy('glint_traces.user_id')
                ->orderByDesc('total_cost')
                ->paginate(25)
                ->withQueryString();
        }, collect());

        return View::make('glint::users.index', compact('users', 'search', 'period', 'fromDate', 'toDate'));
    }

    public function show(Request $request, string $userId): ViewContract
    {
        $period   = $request->string('period')->toString() ?: 'today';
        $fromDate = $request->string('from')->toString();
        $toDate   = $request->string('to')->toString();

        [$fromDt, $toDt] = $this->resolveDateRange($period, $fromDate, $toDate);

        $statsRow = rescue(
            fn () => GlintTrace::query()
                ->select([
                    DB::raw('COUNT(DISTINCT glint_traces.id) as trace_count'),
                    DB::raw('SUM(glint_generations.cost_usd) as total_cost'),
                    DB::raw('AVG(glint_traces.duration_ms) as avg_duration'),
                    DB::raw('SUM(glint_generations.total_tokens) as total_tokens'),
                    DB::raw("COUNT(CASE WHEN glint_traces.status = 'error' THEN 1 END) as error_count"),
                ])
                ->leftJoin('glint_generations', 'glint_generations.trace_id', '=', 'glint_traces.id')
                ->where('glint_traces.user_id', $userId)
                ->first(),
            null
        );

        $attrs = $statsRow ? $statsRow->getAttributes() : [];
        $stats = [
            'trace_count'  => isset($attrs['trace_count']) && is_numeric($attrs['trace_count']) ? (int) $attrs['trace_count'] : 0,
            'total_cost'   => isset($attrs['total_cost']) && is_numeric($attrs['total_cost']) ? (float) $attrs['total_cost'] : 0.0,
            'avg_duration' => isset($attrs['avg_duration']) && is_numeric($attrs['avg_duration']) ? (int) $attrs['avg_duration'] : 0,
            'total_tokens' => isset($attrs['total_tokens']) && is_numeric($attrs['total_tokens']) ? (int) $attrs['total_tokens'] : 0,
            'error_count'  => isset($attrs['error_count']) && is_numeric($attrs['error_count']) ? (int) $attrs['error_count'] : 0,
        ];

        $traces = rescue(function () use ($userId, $fromDt, $toDt) {
            return GlintTrace::query()
                ->where('user_id', $userId)
                ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                ->latest('started_at')
                ->paginate(25)
                ->withQueryString();
        }, collect());

        return View::make('glint::users.show', compact('userId', 'stats', 'traces', 'period', 'fromDate', 'toDate'));
    }
}
