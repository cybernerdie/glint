<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TopTraceUseCasesQuery
{
    public function __construct(
        private readonly ?Carbon $fromDt,
        private readonly ?Carbon $toDt,
        private readonly string $provider = '',
    ) {}

    /** @return Collection<int, GlintTrace> */
    public function get(): Collection
    {
        return GlintTrace::query()
            ->select([
                'glint_traces.name',
                DB::raw('COUNT(DISTINCT glint_traces.id) as trace_count'),
                DB::raw('SUM(glint_generations.cost_usd) as total_cost'),
                DB::raw('SUM(glint_generations.total_tokens) as total_tokens'),
            ])
            ->leftJoin('glint_generations', 'glint_generations.trace_id', '=', 'glint_traces.id')
            ->whereNotNull('glint_traces.name')
            ->when($this->provider !== '', fn ($q) => $q->where('glint_generations.provider', $this->provider))
            ->when($this->fromDt, fn ($q) => $q->where('glint_traces.started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('glint_traces.started_at', '<=', $this->toDt))
            ->groupBy('glint_traces.name')
            ->orderByDesc('total_cost')
            ->limit(20)
            ->get();
    }
}
