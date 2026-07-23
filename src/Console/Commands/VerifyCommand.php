<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Cybernerdie\Glint\Pricing\PricingRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

final class VerifyCommand extends Command
{
    protected $signature = 'glint:verify';

    protected $description = 'Verify the Glint installation and configuration';

    public function handle(PricingRegistry $pricing): int
    {
        $this->components->info('Verifying Laravel Glint installation...');
        $this->newLine();

        $issues = 0;

        $enabled = Config::boolean('glint.enabled', false);

        if ($enabled) {
            $this->components->twoColumnDetail('Glint recording', '<fg=green;options=bold>ENABLED</>');
        } else {
            $this->components->twoColumnDetail('Glint recording', '<fg=yellow;options=bold>DISABLED</>');
            $this->line('  <fg=yellow>→</> Set <fg=cyan>GLINT_ENABLED=true</> in .env to start recording.');
            $issues++;
        }

        $required = [
            'glint_traces', 'glint_spans', 'glint_generations',
            'glint_aggregates', 'glint_alert_rules', 'glint_alert_events',
        ];

        $missing = array_values(array_filter($required, fn (string $t) => ! Schema::hasTable($t)));

        if ($missing === []) {
            $this->components->twoColumnDetail('Database tables', '<fg=green;options=bold>ALL PRESENT</>');
        } else {
            $this->components->twoColumnDetail(
                'Database tables',
                '<fg=red;options=bold>MISSING: '.implode(', ', $missing).'</>'
            );
            $this->line('  <fg=yellow>→</> Run: <fg=cyan>php artisan migrate</>');
            $issues++;
        }

        $providersPath = base_path('bootstrap/providers.php');
        $providersContent = file_exists($providersPath) ? (string) file_get_contents($providersPath) : '';

        $registered = str_contains($providersContent, 'App\\Providers\\GlintServiceProvider');

        if ($registered) {
            $this->components->twoColumnDetail('App service provider', '<fg=green;options=bold>REGISTERED</>');
        } else {
            $this->components->twoColumnDetail('App service provider', '<fg=yellow;options=bold>NOT FOUND</>');
            $this->line('  <fg=yellow>→</> Run: <fg=cyan>php artisan glint:install</>');
            $this->line('  <fg=yellow>  </> Without it the dashboard is publicly accessible.');
            $issues++;
        }

        $mode = Config::string('glint.recording.mode', 'queue');
        $this->components->twoColumnDetail('Recording mode', "<fg=cyan>{$mode}</>");

        if ($mode === 'queue') {
            $rawConn = Config::get('glint.queue.connection');
            $conn = is_string($rawConn) && $rawConn !== '' ? $rawConn : 'default';
            $rawQueue = Config::get('glint.queue.queue');
            $queue = is_string($rawQueue) && $rawQueue !== '' ? $rawQueue : 'default';
            $this->components->twoColumnDetail('Queue', "<fg=cyan>{$conn} / {$queue}</>");
            $this->line("  <fg=yellow>Note:</> Ensure a worker is running: <fg=cyan>php artisan queue:work --queue={$queue}</>");
        }

        $driversRaw = Config::get('glint.drivers', ['http']);
        $drivers = is_array($driversRaw)
            ? array_values(array_filter(array_map(fn (mixed $d): ?string => is_string($d) ? $d : null, $driversRaw)))
            : ['http'];
        $this->components->twoColumnDetail('Active drivers', '<fg=cyan>'.implode(', ', $drivers).'</>');

        $count = 0;
        foreach ($pricing->all() as $models) {
            $count += count($models);
        }
        $providerCount = count($pricing->all());

        if ($count > 0) {
            $this->components->twoColumnDetail(
                'Pricing registry',
                "<fg=green;options=bold>{$count} models from {$providerCount} providers</>"
            );
        } else {
            $this->components->twoColumnDetail('Pricing registry', '<fg=yellow;options=bold>EMPTY — cost tracking disabled</>');
            $this->line('  <fg=yellow>→</> Run: <fg=cyan>php artisan glint:pricing</> to inspect loaded prices.');
        }

        $this->newLine();

        if ($issues === 0) {
            $this->components->info('All checks passed. Glint is ready.');

            return self::SUCCESS;
        }

        $this->components->warn("{$issues} issue(s) found — see above for fixes.");

        return self::FAILURE;
    }
}
