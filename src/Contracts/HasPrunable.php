<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface HasPrunable
{
    /** @return Builder<TModel> */
    public function prunable(): Builder;
}
