<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Cybernerdie\Glint\Contracts\HasPrunable;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintAlertEvent;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

final class PruneCommand extends Command
{
    /** Minimum number of days that must remain before records can be pruned. */
    private const MIN_RETENTION_DAYS = 1;

    protected $signature = 'glint:prune {--days= : Override retention days (minimum 1)} {--force : Skip confirmation}';

    protected $description = 'Prune old Glint records';

    public function handle(): int
    {
        // Validate --days override when provided
        $daysOption = $this->option('days');

        if ($daysOption !== null) {
            $days = (int) $daysOption;

            if ($days < self::MIN_RETENTION_DAYS) {
                $this->components->error(
                    '--days must be at least '.self::MIN_RETENTION_DAYS.". Refusing to prune with a value of {$days} to prevent accidental data loss."
                );

                return self::FAILURE;
            }

            // Apply override to config so models pick it up via Config::integer()
            config(['glint.retention.traces_days' => $days]);
            config(['glint.retention.aggregates_days' => $days]);
            config(['glint.retention.alert_days' => $days]);
        }

        // Guard against misconfigured retention values already in config.
        // Each model reads its own config key, so we check both.
        $tracesDays = Config::integer('glint.retention.traces_days', 30);
        $aggregatesDays = Config::integer('glint.retention.aggregates_days', 365);

        if ($tracesDays < self::MIN_RETENTION_DAYS) {
            $this->components->error(
                "glint.retention.traces_days is set to {$tracesDays}, which is below the minimum of ".self::MIN_RETENTION_DAYS.'. Update your config before pruning.'
            );

            return self::FAILURE;
        }

        if ($aggregatesDays < self::MIN_RETENTION_DAYS) {
            $this->components->error(
                "glint.retention.aggregates_days is set to {$aggregatesDays}, which is below the minimum of ".self::MIN_RETENTION_DAYS.'. Update your config before pruning.'
            );

            return self::FAILURE;
        }

        $this->components->info('Pruning Glint records...');

        $this->pruneModel(new GlintTrace, 'glint_traces');
        $this->pruneModel(new GlintSpan, 'glint_spans');
        $this->pruneModel(new GlintGeneration, 'glint_generations');
        $this->pruneModel(new GlintAggregate, 'glint_aggregates');
        $this->pruneModel(new GlintAlertEvent, 'glint_alert_events');

        $this->newLine();
        $this->components->info('Pruning complete.');

        return self::SUCCESS;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  HasPrunable<TModel>  $model
     */
    private function pruneModel(HasPrunable $model, string $table): void
    {
        try {
            $deleted = $model->prunable()->delete();
            $count = is_numeric($deleted) ? (int) $deleted : 0;
            $this->components->twoColumnDetail($table, "<fg=green>{$count} records pruned</>");
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail($table, "<fg=yellow>skipped — {$e->getMessage()}</>");
        }
    }
}
