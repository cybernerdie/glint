<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class TopTraceNamesQuery
{
    public function __construct(
        private ?Carbon $fromDt,
        private ?Carbon $toDt,
    ) {}

    /** @return Collection<int, GlintTrace> */
    public function get(): Collection
    {
        return GlintTrace::query()
            ->select(['name', DB::raw('COUNT(*) as trace_count')])
            ->whereNotNull('name')
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->groupBy('name')
            ->orderByDesc('trace_count')
            ->limit(8)
            ->get();
    }
}
