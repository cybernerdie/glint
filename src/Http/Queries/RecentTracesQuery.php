<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class RecentTracesQuery
{
    public function __construct(
        private ?Carbon $fromDt,
        private ?Carbon $toDt,
    ) {}

    /** @return Collection<int, GlintTrace> */
    public function get(): Collection
    {
        return GlintTrace::query()
            ->latest('started_at')
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->limit(10)
            ->get();
    }
}
