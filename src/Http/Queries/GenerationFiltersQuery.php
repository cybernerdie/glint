<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Support\Collection;

final class GenerationFiltersQuery
{
    /**
     * @return array{providers: Collection<int, mixed>, models: Collection<int, mixed>}
     */
    public function get(): array
    {
        $rows = GlintGeneration::query()
            ->select(['provider', 'model'])
            ->distinct()
            ->orderBy('provider')
            ->orderBy('model')
            ->get();

        return [
            'providers' => $rows->pluck('provider')->unique()->values(),
            'models' => $rows->pluck('model')->unique()->values(),
        ];
    }
}
