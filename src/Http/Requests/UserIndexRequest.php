<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Requests;

final class UserIndexRequest extends BaseFilterRequest
{
    public function search(): string
    {
        return $this->string('search')->toString();
    }
}
