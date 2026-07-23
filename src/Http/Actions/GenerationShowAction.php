<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Models\GlintGeneration;

final class GenerationShowAction
{
    /** @return array<string, mixed> */
    public function handle(string $generationId): array
    {
        $generation = rescue(fn () => GlintGeneration::query()->where('id', $generationId)->first(), null);

        if ($generation === null) {
            abort(404);
        }

        return compact('generation');
    }
}
