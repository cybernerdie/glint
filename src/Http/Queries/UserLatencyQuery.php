<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class UserLatencyQuery
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
                'user_id',
                DB::raw('COUNT(*) as trace_count'),
                DB::raw('AVG(duration_ms) as avg_duration'),
                DB::raw('MAX(duration_ms) as max_duration'),
            ])
            ->whereNotNull('user_id')
            ->whereNotNull('duration_ms')
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->groupBy('user_id')
            ->orderByDesc('max_duration')
            ->limit(20)
            ->get();
    }
}
