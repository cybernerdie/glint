<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Http\Concerns\ResolvesDateRange;
use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

final class GenerationsController
{
    use ResolvesDateRange;

    public function index(Request $request): ViewContract
    {
        $provider = $request->string('provider')->toString();
        $model = $request->string('model')->toString();
        $status = $request->string('status')->toString();
        $period = $this->normalizePeriod($request->string('period')->toString());
        $fromDate = $request->string('from')->toString();
        $toDate = $request->string('to')->toString();

        [$fromDt, $toDt] = $this->resolveDateRange($period, $fromDate, $toDate);

        $generations = rescue(function () use ($provider, $model, $status, $fromDt, $toDt) {
            $query = GlintGeneration::query()->latest('started_at');

            if ($provider !== '') {
                $query->where('provider', $provider);
            }

            if ($model !== '') {
                $query->where('model', $model);
            }

            if ($status !== '' && in_array($status, ['success', 'error', 'pending'], true)) {
                $query->where('status', $status);
            }

            $this->applyDateRange($query, $fromDt, $toDt);

            return $query->paginate(25)->withQueryString();
        }, collect());

        $filters = rescue(function () {
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
        }, ['providers' => collect(), 'models' => collect()]);

        $providers = $filters['providers'];
        $models = $filters['models'];
        $statuses = RecordStatus::cases();

        return View::make('glint::generations.index', compact(
            'generations', 'provider', 'model', 'status', 'providers', 'models', 'statuses',
            'period', 'fromDate', 'toDate'
        ));
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
