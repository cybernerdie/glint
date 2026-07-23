<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Http\Queries\UserStatsQuery;
use Cybernerdie\Glint\Http\Requests\UserShowRequest;
use Cybernerdie\Glint\Models\GlintTrace;

final class UserShowAction
{
    /** @return array<string, mixed> */
    public function handle(UserShowRequest $request, string $userId): array
    {
        [$fromDt, $toDt] = $request->dateRange();

        return [
            'stats'    => rescue(fn () => (new UserStatsQuery($userId, $fromDt, $toDt))->get(), UserStatsQuery::empty()),
            'traces'   => rescue(
                fn () => GlintTrace::query()
                    ->where('user_id', $userId)
                    ->when($fromDt, fn ($q) => $q->where('started_at', '>=', $fromDt))
                    ->when($toDt, fn ($q) => $q->where('started_at', '<=', $toDt))
                    ->latest('started_at')
                    ->paginate(25)
                    ->withQueryString(),
                collect()
            ),
            'userId'   => $userId,
            'period'   => $request->period(),
            'fromDate' => $request->fromDate(),
            'toDate'   => $request->toDate(),
        ];
    }
}
