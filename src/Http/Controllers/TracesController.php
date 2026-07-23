<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Http\Concerns\ResolvesDateRange;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

final class TracesController
{
    use ResolvesDateRange;

    public function index(Request $request): ViewContract
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $userId = $request->string('user_id')->toString();
        $period = $this->normalizePeriod($request->string('period')->toString());
        $fromDate = $request->string('from')->toString();
        $toDate = $request->string('to')->toString();

        [$fromDt, $toDt] = $this->resolveDateRange($period, $fromDate, $toDate);

        $traces = rescue(function () use ($search, $status, $userId, $fromDt, $toDt) {
            $query = GlintTrace::query()->latest('started_at');

            if ($search !== '') {
                $query->where('name', 'like', '%'.$search.'%');
            }

            if ($status !== '' && in_array($status, ['success', 'error', 'pending'], true)) {
                $query->where('status', $status);
            }

            if ($userId !== '') {
                $query->where('user_id', $userId);
            }

            $this->applyDateRange($query, $fromDt, $toDt);

            return $query->paginate(25)->withQueryString();
        }, collect());

        $statuses = RecordStatus::cases();

        return View::make('glint::traces.index', compact(
            'traces', 'search', 'status', 'userId', 'statuses', 'period', 'fromDate', 'toDate'
        ));
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
