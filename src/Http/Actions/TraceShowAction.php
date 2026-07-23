<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Models\GlintTrace;

final class TraceShowAction
{
    /** @return array<string, mixed> */
    public function handle(string $traceId): array
    {
        $trace = rescue(fn () => GlintTrace::query()->where('id', $traceId)->first(), null);

        if ($trace === null) {
            abort(404);
        }

        $spans       = rescue(fn () => $trace->spans()->orderBy('started_at')->limit(500)->get(), collect());
        $generations = rescue(fn () => $trace->generations()->orderBy('started_at')->limit(500)->get(), collect());

        return compact('trace', 'spans', 'generations');
    }
}
