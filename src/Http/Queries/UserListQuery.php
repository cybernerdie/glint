<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class UserListQuery
{
    public function __construct(
        private readonly ?Carbon $fromDt,
        private readonly ?Carbon $toDt,
        private readonly string $search = '',
    ) {}

    /** @return LengthAwarePaginator<GlintTrace> */
    public function get(): LengthAwarePaginator
    {
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
            ->when($this->search !== '', fn ($q) => $q->where('glint_traces.user_id', 'like', '%'.$this->search.'%'))
            ->when($this->fromDt, fn ($q) => $q->where('glint_traces.started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('glint_traces.started_at', '<=', $this->toDt))
            ->groupBy('glint_traces.user_id')
            ->orderByDesc('total_cost')
            ->paginate(25)
            ->withQueryString();
    }
}
