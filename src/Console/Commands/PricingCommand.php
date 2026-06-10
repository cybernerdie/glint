<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Cybernerdie\Glint\Pricing\PricingRegistry;
use Illuminate\Console\Command;

final class PricingCommand extends Command
{
    protected $signature = 'glint:pricing {--provider= : Filter by provider name}';

    protected $description = 'Display the Glint pricing registry';

    public function __construct(private readonly PricingRegistry $registry)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $all = $this->registry->all();

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
}
