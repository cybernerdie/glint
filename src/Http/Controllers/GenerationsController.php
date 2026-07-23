<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

final class GenerationsController
{
    public function index(Request $request): ViewContract
    {
        $provider = $request->string('provider')->toString();
        $model = $request->string('model')->toString();

        $generations = rescue(function () use ($provider, $model) {
            $query = GlintGeneration::query()->latest('started_at');

            if ($provider !== '') {
                $query->where('provider', $provider);
            }

            if ($model !== '') {
                $query->where('model', $model);
            }

            return $query->cursorPaginate(25)->withQueryString();
        }, collect());

        // Single query for both dropdowns — pluck and split in PHP
        // rather than issuing two separate SELECT DISTINCT round-trips.
        $filters = rescue(function () {
            $rows = GlintAggregate::query()
                ->select(['provider', 'model'])
                ->distinct()
                ->orderBy('provider')
                ->orderBy('model')
                ->get();

            return [
                'providers' => $rows->pluck('provider')->unique()->values(),
                'models' => $rows->pluck('model')->unique()->values(),
            ];
        }, ['providers' => collect(), 'models' => collect()]);

        $providers = $filters['providers'];
        $models = $filters['models'];

        return View::make('glint::generations.index', compact('generations', 'provider', 'model', 'providers', 'models'));
    }

    public function show(string $generationId): ViewContract
    {
        $generation = rescue(
            fn () => GlintGeneration::query()->where('id', $generationId)->first(),
            null
        );

        if ($generation === null) {
            abort(404);
        }

        return View::make('glint::generations.show', compact('generation'));
    }
}
