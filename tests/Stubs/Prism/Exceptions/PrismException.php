<?php

declare(strict_types=1);

namespace Prism\Prism\Exceptions;

use Exception;

class PrismException extends Exception
{
    public static function unsupportedProviderAction(string $method, string $provider): self
    {
        return new self(sprintf(
            '%s is not supported by %s',
            ucfirst($method),
            $provider,
        ));
    }
}
