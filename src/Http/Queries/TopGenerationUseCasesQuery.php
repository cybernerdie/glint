<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class TopGenerationUseCasesQuery
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
            ->select([
                'name',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(cost_usd) as total_cost'),
                DB::raw('SUM(total_tokens) as total_tokens'),
            ])
            ->whereNotNull('name')
            ->when($this->provider !== '', fn ($q) => $q->where('provider', $this->provider))
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->groupBy('name')
            ->orderByDesc('total_cost')
            ->limit(20)
            ->get();
    }
}
