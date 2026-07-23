<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Http\Queries\UserListQuery;
use Cybernerdie\Glint\Http\Requests\UserIndexRequest;

final class UserIndexAction
{
    /** @return array<string, mixed> */
    public function handle(UserIndexRequest $request): array
    {
        [$fromDt, $toDt] = $request->dateRange();

        return [
            'users'    => rescue(fn () => (new UserListQuery($fromDt, $toDt, $request->search()))->get(), collect()),
            'search'   => $request->search(),
            'period'   => $request->period(),
            'fromDate' => $request->fromDate(),
            'toDate'   => $request->toDate(),
        ];
    }
}
