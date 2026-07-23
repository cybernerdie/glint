<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class TopModelCostsQuery
{
    public function __construct(
        private ?Carbon $fromDt,
        private ?Carbon $toDt,
    ) {}

    /** @return Collection<int, GlintGeneration> */
    public function get(): Collection
    {
        return GlintGeneration::query()
            ->select([
                'model',
                'provider',
                DB::raw('SUM(cost_usd) as total_cost'),
                DB::raw('SUM(total_tokens) as total_tokens'),
                DB::raw('COUNT(*) as total_requests'),
            ])
            ->whereNotNull('cost_usd')
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->groupBy('model', 'provider')
            ->orderByDesc('total_cost')
            ->limit(6)
            ->get();
    }
}
