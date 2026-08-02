<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Illuminate\Console\Command;

final class PublishCommand extends Command
{
    protected $signature = 'glint:publish
                            {--config : Publish the configuration file}
                            {--migrations : Publish the database migrations}
                            {--views : Publish the dashboard views}
                            {--pricing : Publish the pricing JSON file}
                            {--provider : Publish the GlintServiceProvider stub}
                            {--force : Overwrite existing files}
                            {--all : Publish all assets}';

    protected $description = 'Publish Glint assets and views';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $publishAll = $this->option('all')
            || (
                ! $this->option('config')
                && ! $this->option('migrations')
                && ! $this->option('views')
                && ! $this->option('pricing')
                && ! $this->option('provider')
            );

        if ($publishAll || $this->option('config')) {
            $this->components->task('Publishing configuration', function () use ($force) {
                $this->callSilently('vendor:publish', array_filter([
                    '--tag' => 'glint-config',
                    '--force' => $force ?: null,
                ]));
            });
        }

        if ($publishAll || $this->option('migrations')) {
            $this->components->task('Publishing migrations', function () use ($force) {
                $this->callSilently('vendor:publish', array_filter([
                    '--tag' => 'glint-migrations',
                    '--force' => $force ?: null,
                ]));
            });
        }

        if ($publishAll || $this->option('views')) {
            $this->components->task('Publishing views', function () use ($force) {
                $this->callSilently('vendor:publish', array_filter([
                    '--tag' => 'glint-views',
                    '--force' => $force ?: null,
                ]));
            });
        }

        if ($publishAll || $this->option('pricing')) {
            $this->components->task('Publishing pricing registry', function () use ($force) {
                $this->callSilently('vendor:publish', array_filter([
                    '--tag' => 'glint-pricing',
                    '--force' => $force ?: null,
                ]));
            });
        }

        if ($publishAll || $this->option('provider')) {
            $this->components->task('Publishing GlintServiceProvider', function () use ($force) {
                $this->callSilently('vendor:publish', array_filter([
                    '--tag' => 'glint-provider',
                    '--force' => $force ?: null,
                ]));
            });
        }

        $this->components->info('Glint assets published successfully.');

        return self::SUCCESS;
    }
}
