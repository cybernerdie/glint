<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintAlertEvent;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

final class ClearCommand extends Command
{
    protected $signature = 'glint:clear {--force : Skip confirmation}';

    protected $description = 'Delete all Glint data';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will delete ALL Glint data. Are you sure?')) {
            $this->components->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        $this->components->info('Clearing all Glint data...');

        /** @var array<class-string<Model>, string> $models */
        $models = [
            GlintTrace::class => 'glint_traces',
            GlintSpan::class => 'glint_spans',
            GlintGeneration::class => 'glint_generations',
            GlintAggregate::class => 'glint_aggregates',
            GlintAlertRule::class => 'glint_alert_rules',
            GlintAlertEvent::class => 'glint_alert_events',
        ];

        foreach ($models as $modelClass => $table) {
            try {
                $deleted = $modelClass::query()->delete();
                $count = is_numeric($deleted) ? (int) $deleted : 0;
                $this->components->twoColumnDetail($table, "<fg=green>{$count} records deleted</>");
            } catch (\Throwable $e) {
                $this->components->twoColumnDetail($table, "<fg=yellow>skipped — {$e->getMessage()}</>");
            }
        }

        $this->newLine();
        $this->components->info('All Glint data cleared.');

        return self::SUCCESS;
    }
}
