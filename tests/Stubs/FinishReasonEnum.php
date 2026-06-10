<?php

declare(strict_types=1);

namespace Tests\Stubs;

enum FinishReasonEnum: string
{
    case Stop = 'stop';
    case Length = 'length';
}
