<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @phpstan-type AggregateRow array{provider: string, model: string, periodAt: string, total: int, successful: int, failed: int, totalTokens: int, promptTokens: int, completionTokens: int, totalCost: float, durationSum: int}
 */
final class RecalcAggregatesCommand extends Command
{
    protected $signature = 'glint:recalc-aggregates
                            {--period=hourly : Period to recalculate (hourly|daily|weekly|monthly)}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}';

    protected $description = 'Rebuild the glint_aggregates table from raw generation data';

    /** @var array<string, string> */
    private const PERIOD_MAP = [
        'hourly' => 'hour',
        'daily' => 'day',
        'weekly' => 'week',
        'monthly' => 'month',
        'hour' => 'hour',
        'day' => 'day',
        'week' => 'week',
        'month' => 'month',
    ];

    /** @var array<string, string|null> */
    private const TRUNCATE_FORMAT = [
        'hour' => 'Y-m-d H:00:00',
        'day' => 'Y-m-d 00:00:00',
        'week' => null, // handled separately
        'month' => 'Y-m-01 00:00:00',
    ];

    public function handle(): int
    {
        $periodRaw = $this->option('period');
        $periodOption = is_string($periodRaw) ? $periodRaw : 'hourly';

        if (! isset(self::PERIOD_MAP[$periodOption])) {
            $this->components->error("Invalid period \"{$periodOption}\". Valid options: hour, day, week, month.");

            return self::FAILURE;
        }

        $period = self::PERIOD_MAP[$periodOption];

        $query = GlintGeneration::query();

        $fromRaw = $this->option('from');
        $toRaw = $this->option('to');

        $fromDate = null;
        $toDate = null;

        if (is_string($fromRaw)) {
            try {
                $fromDate = Carbon::parse($fromRaw)->startOfDay();
            } catch (\Exception) {
                $this->components->error("Invalid --from date \"{$fromRaw}\". Expected format: Y-m-d.");

                return self::FAILURE;
            }
        }

        if (is_string($toRaw)) {
            try {
                $toDate = Carbon::parse($toRaw)->endOfDay();
            } catch (\Exception) {
                $this->components->error("Invalid --to date \"{$toRaw}\". Expected format: Y-m-d.");

                return self::FAILURE;
            }
        }

        if ($fromDate !== null && $toDate !== null && $fromDate->isAfter($toDate)) {
            $this->components->error('--from date must not be after --to date.');

            return self::FAILURE;
        }

        if ($fromDate !== null) {
            $query->where('started_at', '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->where('started_at', '<=', $toDate);
        }

        if ($query->doesntExist()) {
            $this->components->warn('No generation records found in the given window. Nothing to recalculate.');

            return self::SUCCESS;
        }

        $this->components->info("Recalculating {$periodOption} aggregates...");

        /** @var array<string, AggregateRow> $buckets */
        $buckets = [];

        $query->select(['id', 'provider', 'model', 'status', 'prompt_tokens', 'completion_tokens', 'cost_usd', 'duration_ms', 'started_at'])
            ->chunkById(500, function ($generations) use ($period, &$buckets) {
                /** @var Collection<int, GlintGeneration> $generations */
                foreach ($generations as $gen) {
                    $key = $gen->provider.'|'.$gen->model.'|'.$this->bucketFor($period, $gen->started_at);

                    $bucket = $buckets[$key] ??= [
                        'provider' => (string) $gen->provider,
                        'model' => (string) $gen->model,
                        'periodAt' => $this->bucketFor($period, $gen->started_at),
                        'total' => 0,
                        'successful' => 0,
                        'failed' => 0,
                        'totalTokens' => 0,
                        'promptTokens' => 0,
                        'completionTokens' => 0,
                        'totalCost' => 0.0,
                        'durationSum' => 0,
                    ];

                    $bucket['total']++;
                    $bucket['successful'] += $gen->status === RecordStatus::Success ? 1 : 0;
                    $bucket['failed'] += $gen->status === RecordStatus::Error ? 1 : 0;
                    $bucket['promptTokens'] += (int) $gen->prompt_tokens;
                    $bucket['completionTokens'] += (int) $gen->completion_tokens;
                    $bucket['totalTokens'] += ((int) $gen->prompt_tokens) + ((int) $gen->completion_tokens);
                    $bucket['totalCost'] += (float) $gen->cost_usd;
                    $bucket['durationSum'] += (int) $gen->duration_ms;

                    $buckets[$key] = $bucket;
                }
            });

        foreach ($buckets as $bucket) {
            GlintAggregate::updateOrCreate(
                [
                    'period' => $period,
                    'period_at' => $bucket['periodAt'],
                    'provider' => $bucket['provider'],
                    'model' => $bucket['model'],
                    'user_id' => GlintAggregate::GlobalDimension,
                    'team_id' => GlintAggregate::GlobalDimension,
                ],
                [
                    'total_requests' => $bucket['total'],
                    'successful_requests' => $bucket['successful'],
                    'failed_requests' => $bucket['failed'],
                    'total_tokens' => $bucket['totalTokens'],
                    'prompt_tokens' => $bucket['promptTokens'],
                    'completion_tokens' => $bucket['completionTokens'],
                    'total_cost_usd' => $bucket['totalCost'],
                    'avg_duration_ms' => $bucket['total'] > 0 ? intdiv($bucket['durationSum'], $bucket['total']) : null,
                ]
            );
        }

        $this->components->info('Recalculated '.count($buckets).' aggregate bucket(s).');

        return self::SUCCESS;
    }

    private function bucketFor(string $period, mixed $startedAt): string
    {
        $dt = $startedAt instanceof Carbon ? $startedAt : Carbon::parse(is_string($startedAt) ? $startedAt : null);

        if ($period === 'week') {
            return $dt->startOfWeek()->format('Y-m-d 00:00:00');
        }

        $format = self::TRUNCATE_FORMAT[$period] ?? 'Y-m-d 00:00:00';

        return $dt->format($format);
    }
}
