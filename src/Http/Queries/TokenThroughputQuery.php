<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class TokenThroughputQuery
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
                DB::raw('COUNT(*) as request_count'),
                DB::raw('AVG(total_tokens) as avg_tokens'),
                DB::raw('AVG(duration_ms) as avg_duration'),
                DB::raw('CASE WHEN SUM(duration_ms) > 0 THEN SUM(total_tokens) * 1000.0 / SUM(duration_ms) ELSE 0 END as tokens_per_second'),
            ])
            ->whereNotNull('duration_ms')
            ->where('duration_ms', '>', 0)
            ->whereNotNull('total_tokens')
            ->when($this->fromDt, fn ($q) => $q->where('started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('started_at', '<=', $this->toDt))
            ->groupBy('model', 'provider')
            ->orderByDesc('tokens_per_second')
            ->get();
    }
}
