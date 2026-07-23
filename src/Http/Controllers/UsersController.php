<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Actions\UserIndexAction;
use Cybernerdie\Glint\Http\Actions\UserShowAction;
use Cybernerdie\Glint\Http\Requests\BaseFilterRequest;
use Cybernerdie\Glint\Http\Requests\UserIndexRequest;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

final class UsersController
{
    public function index(UserIndexRequest $request, UserIndexAction $action): ViewContract
    {
        return View::make('glint::users.index', $action->handle($request));
    }

    public function show(BaseFilterRequest $request, string $userId, UserShowAction $action): ViewContract
    {
        return View::make('glint::users.show', $action->handle($request, $userId));
    }
}
