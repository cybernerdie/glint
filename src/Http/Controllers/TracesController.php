<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

final class TracesController
{
    public function index(Request $request): ViewContract
    {
        $search = $request->string('search')->toString();

        $traces = rescue(function () use ($search) {
            $query = GlintTrace::query()->latest('started_at');

            if ($search !== '') {
                $query->where('name', 'like', '%'.$search.'%');
            }

            return $query->cursorPaginate(25)->withQueryString();
        }, collect());

        return View::make('glint::traces.index', compact('traces', 'search'));
    }

    public function show(string $traceId): ViewContract
    {
        $trace = rescue(
            fn () => GlintTrace::query()->where('id', $traceId)->first(),
            null
        );

        if ($trace === null) {
            abort(404);
        }

        // Cap at 500 rows each — agent-loop traces can accumulate hundreds of
        // spans/generations and loading all at once would exhaust memory.
        $spans = rescue(
            fn () => $trace->spans()->orderBy('started_at')->limit(500)->get(),
            collect()
        );

        $generations = rescue(
            fn () => $trace->generations()->orderBy('started_at')->limit(500)->get(),
            collect()
        );

        return View::make('glint::traces.show', compact('trace', 'spans', 'generations'));
    }
}
