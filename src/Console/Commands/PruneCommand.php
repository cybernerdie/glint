<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Cybernerdie\Glint\Contracts\HasPrunable;
use Cybernerdie\Glint\Events\GlintDataPruned;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintAlertEvent;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintSpan;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

final class PruneCommand extends Command
{
    private const MIN_RETENTION_DAYS = 1;

    protected $signature = 'glint:prune {--days= : Override retention days (minimum 1)} {--force : Skip confirmation}';

    protected $description = 'Prune old Glint records';

    public function handle(): int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null) {
            $days = (int) $daysOption;

            if ($days < self::MIN_RETENTION_DAYS) {
                $this->components->error(
                    '--days must be at least '.self::MIN_RETENTION_DAYS.". Refusing to prune with a value of {$days} to prevent accidental data loss."
                );

                return self::FAILURE;
            }

            config(['glint.retention.traces_days' => $days]);
            config(['glint.retention.aggregates_days' => $days]);
            config(['glint.retention.alert_days' => $days]);
        }

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

        $deletedByTable = [
            'glint_traces' => $this->pruneModel(new GlintTrace, 'glint_traces'),
            'glint_spans' => $this->pruneModel(new GlintSpan, 'glint_spans'),
            'glint_generations' => $this->pruneModel(new GlintGeneration, 'glint_generations'),
            'glint_aggregates' => $this->pruneModel(new GlintAggregate, 'glint_aggregates'),
            'glint_alert_events' => $this->pruneModel(new GlintAlertEvent, 'glint_alert_events'),
        ];

        event(new GlintDataPruned($deletedByTable));

        $this->newLine();
        $this->components->info('Pruning complete.');

        return self::SUCCESS;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  HasPrunable<TModel>  $model
     */
    private function pruneModel(HasPrunable $model, string $table): int
    {
        try {
            // Delete in ID batches rather than one large DELETE so the table is
            // never locked for long. Avoids DELETE...LIMIT, which SQLite rejects.
            $count = 0;
            do {
                $ids = $model->prunable()->limit(1000)->pluck('id');

                if ($ids->isEmpty()) {
                    break;
                }

                $deleted = $model->prunable()->whereIn('id', $ids->all())->delete();
                $count += is_numeric($deleted) ? (int) $deleted : 0;
            } while ($ids->count() === 1000);

            $this->components->twoColumnDetail($table, "<fg=green>{$count} records pruned</>");

            return $count;
        } catch (\Throwable $e) {
            $this->components->twoColumnDetail($table, "<fg=yellow>skipped — {$e->getMessage()}</>");

            return 0;
        }
    }
}
