<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class TopUserCostsQuery
{
    public function __construct(
        private ?Carbon $fromDt,
        private ?Carbon $toDt,
    ) {}

    /** @return Collection<int, GlintTrace> */
    public function get(): Collection
    {
        return GlintTrace::query()
            ->select([
                'glint_traces.user_id',
                DB::raw('SUM(glint_generations.cost_usd) as total_cost'),
                DB::raw('COUNT(DISTINCT glint_traces.id) as trace_count'),
            ])
            ->leftJoin('glint_generations', 'glint_generations.trace_id', '=', 'glint_traces.id')
            ->whereNotNull('glint_traces.user_id')
            ->when($this->fromDt, fn ($q) => $q->where('glint_traces.started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('glint_traces.started_at', '<=', $this->toDt))
            ->groupBy('glint_traces.user_id')
            ->orderByDesc('total_cost')
            ->limit(8)
            ->get();
    }
}
