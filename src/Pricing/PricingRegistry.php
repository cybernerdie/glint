<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Pricing;

use Illuminate\Support\Facades\Config;

final class PricingRegistry
{
    /** @var array<string, array<string, array{input: float, output: float}>> */
    private array $prices = [];

    private bool $loaded = false;

    public function __construct(private readonly string $pricingPath) {}

    /**
     * Calculate the cost in USD for a given provider, model, and token counts.
     * Returns 0.0 if the provider or model is not in the pricing registry.
     */
    public function costFor(
        string $provider,
        string $model,
        int $promptTokens,
        int $completionTokens,
    ): float {
        $this->ensureLoaded();

        $entry = $this->prices[$provider][$model] ?? null;

        if ($entry === null) {
            return 0.0;
        }

        return round(
            ($promptTokens / 1_000_000 * $entry['input'])
            + ($completionTokens / 1_000_000 * $entry['output']),
            8
        );
    }

    /**
     * Return all pricing data keyed by provider then model.
     *
     * @return array<string, array<string, array{input: float, output: float}>>
     */
    public function all(): array
    {
        $this->ensureLoaded();

        return $this->prices;
    }

    /**
     * Return pricing data for a single provider.
     *
     * @return array<string, array{input: float, output: float}>
     */
    public function forProvider(string $provider): array
    {
        $this->ensureLoaded();

        return $this->prices[$provider] ?? [];
    }

    /**
     * Check whether a provider + model combination exists in the registry.
     */
    public function has(string $provider, string $model): bool
    {
        $this->ensureLoaded();

        return isset($this->prices[$provider][$model]);
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (! file_exists($this->pricingPath)) {
            return;
        }

        $contents = file_get_contents($this->pricingPath);

        if ($contents === false) {
            return;
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            logger()->warning('[Glint] Pricing JSON is invalid: '.json_last_error_msg(), ['path' => $this->pricingPath]);

            return;
        }

        if (! is_array($decoded)) {
            logger()->warning('[Glint] Pricing file must contain a JSON object', ['path' => $this->pricingPath]);

            return;
        }

        $this->prices = $this->parsePrices($decoded);
        $this->prices = $this->mergeOverrides($this->prices);
    }

    /**
     * @param  array<array-key, mixed>  $decoded
     * @return array<string, array<string, array{input: float, output: float}>>
     */
    private function parsePrices(array $decoded): array
    {
        $prices = [];

        foreach ($decoded as $provider => $models) {
            if (! is_string($provider) || str_starts_with($provider, '_') || ! is_array($models)) {
                continue;
            }

            foreach ($models as $model => $entry) {
                if (! is_string($model) || ! is_array($entry)) {
                    continue;
                }

                $input = $entry['input'] ?? null;
                $output = $entry['output'] ?? null;

                if (! is_numeric($input) || ! is_numeric($output)) {
                    continue;
                }

                $prices[$provider][$model] = [
                    'input' => (float) $input,
                    'output' => (float) $output,
                ];
            }
        }

        return $prices;
    }

    /**
     * @param  array<string, array<string, array{input: float, output: float}>>  $prices
     * @return array<string, array<string, array{input: float, output: float}>>
     */
    private function mergeOverrides(array $prices): array
    {
        $overrides = Config::get('glint.pricing_overrides', []);

        if (! is_array($overrides)) {
            return $prices;
        }

        $parsedOverrides = $this->parsePrices($overrides);

        foreach ($parsedOverrides as $provider => $models) {
            foreach ($models as $model => $entry) {
                $prices[$provider][$model] = $entry;
            }
        }

        return $prices;
    }
}
