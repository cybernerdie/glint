<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Enums;

enum RecordStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Error = 'error';
}
