<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Cybernerdie\Glint\Pricing\PricingRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PricingCommand extends Command
{
    protected $signature = 'glint:pricing
                            {--provider= : Filter by provider name}
                            {--unknown : Show provider/model pairs recorded by Glint but missing from the pricing registry}';

    protected $description = 'Display the Glint pricing registry';

    public function handle(PricingRegistry $registry): int
    {
        if ($this->option('unknown')) {
            return $this->showUnknownModels($registry);
        }

        $all = $registry->all();

        $filterProviderRaw = $this->option('provider');
        $filterProvider = is_string($filterProviderRaw) ? $filterProviderRaw : null;

        if ($filterProvider !== null) {
            $all = isset($all[$filterProvider])
                ? [$filterProvider => $all[$filterProvider]]
                : [];
        }

        if (empty($all)) {
            $this->components->warn('No pricing data found'.($filterProvider !== null ? " for provider \"{$filterProvider}\"" : '').'.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($all as $provider => $models) {
            foreach ($models as $model => $prices) {
                $rows[] = [
                    ucfirst((string) $provider),
                    (string) $model,
                    sprintf('$%.6f', (float) $prices['input']),
                    sprintf('$%.6f', (float) $prices['output']),
                ];
            }
        }

        $this->table(
            ['Provider', 'Model', 'Input (per 1M tokens)', 'Output (per 1M tokens)'],
            $rows
        );

        return self::SUCCESS;
    }

    private function showUnknownModels(PricingRegistry $registry): int
    {
        $rows = [];

        DB::table('glint_generations')
            ->selectRaw('provider, model, COUNT(*) as request_count, MAX(started_at) as last_seen_at')
            ->groupBy('provider', 'model')
            ->orderBy('provider')
            ->orderBy('model')
            ->get()
            ->each(function (object $generation) use (&$rows, $registry): void {
                $provider = is_string($generation->provider ?? null) ? $generation->provider : '';
                $model = is_string($generation->model ?? null) ? $generation->model : '';

                if ($registry->has($provider, $model)) {
                    return;
                }

                $rows[] = [
                    $provider,
                    $model,
                    $this->stringValue($generation->request_count ?? 0),
                    $this->stringValue($generation->last_seen_at ?? ''),
                ];
            });

        if ($rows === []) {
            $this->components->info('No unknown priced models found in recorded generations.');

            return self::SUCCESS;
        }

        $this->components->warn('The following recorded provider/model pairs are missing from the pricing registry.');
        $this->table(['Provider', 'Model', 'Requests', 'Last seen'], $rows);
        $this->line('Add entries to config/glint.php pricing_overrides or update the published pricing JSON.');

        return self::SUCCESS;
    }

    private function stringValue(mixed $value): string
    {
        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}
