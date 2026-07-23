<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Requests;

final class GenerationIndexRequest extends BaseFilterRequest
{
    public function provider(): string
    {
        return $this->string('provider')->toString();
    }

    public function model(): string
    {
        return $this->string('model')->toString();
    }

    public function status(): string
    {
        return $this->string('status')->toString();
    }
}
