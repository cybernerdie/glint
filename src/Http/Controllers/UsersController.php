<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Http\Actions\UserIndexAction;
use Cybernerdie\Glint\Http\Actions\UserShowAction;
use Cybernerdie\Glint\Http\Requests\UserIndexRequest;
use Cybernerdie\Glint\Http\Requests\UserShowRequest;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\View;

final class UsersController
{
    public function index(UserIndexRequest $request, UserIndexAction $action): ViewContract
    {
        return View::make('glint::users.index', $action->handle($request));
    }

    public function show(UserShowRequest $request, string $userId, UserShowAction $action): ViewContract
    {
        return View::make('glint::users.show', $action->handle($request, $userId));
    }
}
