<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class CostTrendQuery
{
    public function __construct(
        private readonly ?Carbon $fromDt,
        private readonly ?Carbon $toDt,
        private readonly string $provider = '',
    ) {}

    /** @return Collection<int, GlintGeneration> */
    public function get(): Collection
    {
        return GlintGeneration::query()
            ->selectRaw('DATE(started_at) as date, SUM(cost_usd) as total')
            ->whereNotNull('cost_usd')
            ->when($this->provider !== '', fn ($q) => $q->where('provider', $this->provider))
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->when($this->fromDt === null && $this->toDt === null, fn ($q) => $q->where('started_at', '>=', now()->subDays(30)->startOfDay()))
            ->groupByRaw('DATE(started_at)')
            ->orderBy('date')
            ->get();
    }
}
