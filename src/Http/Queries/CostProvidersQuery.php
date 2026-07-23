<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Support\Collection;

final class CostProvidersQuery
{
    /** @return Collection<int, mixed> */
    public function get(): Collection
    {
        return GlintGeneration::query()
            ->select('provider')
            ->distinct()
            ->orderBy('provider')
            ->pluck('provider');
    }
}
