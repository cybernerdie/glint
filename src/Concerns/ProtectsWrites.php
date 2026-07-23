<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Concerns;

use Illuminate\Support\Facades\Config;

trait ProtectsWrites
{
    /**
     * Run a Glint write. Exceptions are swallowed so Glint never crashes the
     * host application, unless glint.throw_on_exceptions is enabled (debugging).
     */
    private function protectedWrite(callable $callback): mixed
    {
        if (Config::boolean('glint.throw_on_exceptions', false)) {
            return $callback();
        }

        return rescue($callback);
    }
}
