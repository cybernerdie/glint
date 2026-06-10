<?php

declare(strict_types=1);

namespace Illuminate\AI;

class Meta
{
    public function __construct(
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
    ) {}
}
