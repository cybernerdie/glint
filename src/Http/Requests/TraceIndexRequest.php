<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Requests;

final class TraceIndexRequest extends BaseFilterRequest
{
    public function search(): string
    {
        return $this->string('search')->toString();
    }

    public function status(): string
    {
        return $this->string('status')->toString();
    }

    public function userId(): string
    {
        return $this->string('user_id')->toString();
    }
}
