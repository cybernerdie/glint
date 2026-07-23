<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Requests;

final class CostIndexRequest extends BaseFilterRequest
{
    public function provider(): string
    {
        return $this->string('provider')->toString();
    }
}
