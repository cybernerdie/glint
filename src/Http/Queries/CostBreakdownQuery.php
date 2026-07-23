<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class CostBreakdownQuery
{
    public function __construct(
        private ?Carbon $fromDt,
        private ?Carbon $toDt,
        private string $provider = '',
    ) {}

    /** @return Collection<int, GlintGeneration> */
    public function get(): Collection
    {
        return GlintGeneration::query()
            ->selectRaw('provider, model, SUM(cost_usd) as total_cost, COUNT(*) as total_requests, SUM(total_tokens) as total_tokens')
            ->whereNotNull('cost_usd')
            ->when($this->provider !== '', fn ($q) => $q->where('provider', $this->provider))
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->groupBy('provider', 'model')
            ->orderByDesc('total_cost')
            ->get();
    }
}
