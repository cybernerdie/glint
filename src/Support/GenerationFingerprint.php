<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Support;

final class GenerationFingerprint
{
    /**
     * @param  array<int, mixed>|null  $messages
     */
    public static function make(
        string $provider,
        string $model,
        ?array $messages,
        ?float $temperature,
        ?int $maxTokens,
        bool $isStreaming,
    ): string {
        return hash('sha256', (string) json_encode([
            'provider' => strtolower($provider),
            'model' => $model,
            'messages' => self::normalize($messages),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => $isStreaming,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private static function normalize(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $normalized = array_map(self::normalize(...), $value);

        if (! array_is_list($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }
}
