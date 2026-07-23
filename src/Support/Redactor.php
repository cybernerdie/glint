<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Support;

use Illuminate\Support\Facades\Config;

final class Redactor
{
    public function string(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach ($this->patterns() as $pattern) {
            $result = @preg_replace($pattern, '[REDACTED]', $value);

            if ($result === null || preg_last_error() !== PREG_NO_ERROR) {
                logger()->debug("[Glint] Invalid redact_pattern skipped: {$pattern}");

                continue;
            }

            $value = $result;
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>|null  $metadata
     * @return array<array-key, mixed>|null
     */
    public function metadata(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        return $this->array($metadata);
    }

    public function value(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->string($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        return $this->array($value);
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function array(array $value): array
    {
        return array_map(fn (mixed $item): mixed => $this->value($item), $value);
    }

    /** @return array<int, string> */
    private function patterns(): array
    {
        $patterns = Config::get('glint.privacy.redact_patterns', []);

        if (! is_array($patterns)) {
            return [];
        }

        return array_values(array_filter($patterns, is_string(...)));
    }
}
