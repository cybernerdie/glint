<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

final class InstallCommand extends Command
{
    protected $signature = 'glint:install';

    protected $description = 'Install Laravel Glint';

    public function handle(): int
    {
        $this->components->info('Installing Laravel Glint...');

        $configExists = file_exists(config_path('glint.php'));
        $overwriteConfig = ! $configExists || $this->confirm('config/glint.php already exists. Overwrite it?', false);

        $this->components->task('Publishing configuration', function () use ($overwriteConfig) {
            if (! $overwriteConfig) {
                return true;
            }

            $this->callSilently('vendor:publish', [
                '--tag' => 'glint-config',
                '--force' => true,
            ]);

            return true;
        });

        $this->components->task('Publishing migrations', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'glint-migrations',
                '--force' => true,
            ]);
        });

        $this->components->task('Publishing pricing registry', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'glint-pricing',
            ]);
        });

        $migrationFailed = false;

        $this->components->task('Running migrations', function () use (&$migrationFailed): bool {
            try {
                $exitCode = $this->callSilently('migrate');

                if ($exitCode !== 0) {
                    $migrationFailed = true;

                    return false;
                }

                return true;
            } catch (\Throwable) {
                $migrationFailed = true;

                return false;
            }
        });

        if ($migrationFailed) {
            $this->newLine();
            $this->components->warn('Migrations could not run — check your database connection and run `php artisan migrate` manually.');
        }

        $this->components->task('Publishing GlintServiceProvider', function () {
            $this->callSilently('vendor:publish', [
                '--tag' => 'glint-provider',
                '--force' => true,
            ]);
        });

        $this->registerApplicationServiceProvider();

        $this->components->info('Glint installed successfully.');

        $this->newLine();

        $this->line('  <fg=green;options=bold>Auto-instrumentation is active.</>');
        $this->line('  LLM calls are captured automatically — no middleware required.');

        $this->newLine();

        $this->line('  <fg=yellow>Optional:</> register GlintMiddleware to group LLM calls by HTTP request.');
        $this->line('  This adds request context (route, user, duration) to each trace.');
        $this->line('  Without it, generations are still recorded — just without a parent trace.');
        $this->newLine();
        $this->line('    ->withMiddleware(function (Middleware $middleware) {');
        $this->line('        $middleware->append(\Cybernerdie\Glint\Middleware\GlintMiddleware::class);');
        $this->line('    })');

        $this->newLine();

        $this->line('  <fg=yellow>Visit the dashboard at:</> <fg=cyan>/glint</>');

        $this->newLine();

        $mode = Config::string('glint.recording.mode', 'queue');
        if ($mode === 'queue') {
            $rawQueue = Config::get('glint.queue.queue');
            $queue = is_string($rawQueue) && $rawQueue !== '' ? $rawQueue : 'default';
            $this->line('  <fg=yellow>Queue mode is active.</> Start a worker to process recordings:');
            $this->line("    php artisan queue:work --queue={$queue}");
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function registerApplicationServiceProvider(): void
    {
        $providersPath = base_path('bootstrap/providers.php');

        if (! file_exists($providersPath)) {
            return;
        }

        $contents = file_get_contents($providersPath);

        if ($contents === false) {
            return;
        }

        if (str_contains($contents, 'App\\Providers\\GlintServiceProvider')) {
            $this->components->twoColumnDetail(
                'GlintServiceProvider',
                '<fg=yellow;options=bold>ALREADY REGISTERED</>'
            );

            return;
        }

        // strrpos() handles trailing comments, CRLF, and whitespace more robustly than a regex.
        $insertAt = strrpos($contents, '];');

        if ($insertAt === false) {
            $this->components->warn('Could not auto-register GlintServiceProvider — add App\Providers\GlintServiceProvider::class to bootstrap/providers.php manually.');

            return;
        }

        $updated = substr($contents, 0, $insertAt)
            ."    App\\Providers\\GlintServiceProvider::class,\n"
            .substr($contents, $insertAt);

        $result = file_put_contents($providersPath, $updated);

        if ($result === false) {
            $this->components->warn('Could not write to bootstrap/providers.php — add App\Providers\GlintServiceProvider::class manually.');

            return;
        }

        $this->components->twoColumnDetail(
            'GlintServiceProvider registered in bootstrap/providers.php',
            '<fg=green;options=bold>DONE</>'
        );
    }
}
