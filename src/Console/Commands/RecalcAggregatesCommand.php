<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintAggregate;
use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
            $this->components->error("Invalid period \"{$periodOption}\". Valid options: hourly, daily, weekly, monthly.");

            return self::FAILURE;
        }

        $period = self::PERIOD_MAP[$periodOption];

        // Do NOT apply orderBy('started_at') here — chunkById() enforces ORDER BY `id` ASC
        // internally and a conflicting explicit orderBy causes incorrect chunking behaviour.
        // Temporal bucketing is handled per-record inside the chunk callback.
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

        $count = 0;

        $query->select(['id', 'provider', 'model', 'status', 'prompt_tokens', 'completion_tokens', 'cost_usd', 'started_at'])
            ->chunkById(500, function ($generations) use ($period, &$count) {
                /** @var Collection<int, GlintGeneration> $generations */
                $grouped = $generations->groupBy(function ($gen) use ($period) {
                    return $gen->provider.'|'.$gen->model.'|'.$this->bucketFor($period, $gen->started_at);
                });

                foreach ($grouped as $key => $group) {
                    $parts = explode('|', (string) $key, 3);
                    $provider = $parts[0];
                    $model = $parts[1];
                    $periodAt = $parts[2];

                    $total = $group->count();
                    $errors = $group->where('status', RecordStatus::Error)->count();
                    $successful = $group->where('status', RecordStatus::Success)->count();
                    $failed = $errors;

                    GlintAggregate::updateOrCreate(
                        [
                            'period' => $period,
                            'period_at' => $periodAt,
                            'provider' => $provider,
                            'model' => $model,
                            'user_id' => null,
                            'team_id' => null,
                        ],
                        [
                            'total_requests' => $total,
                            'successful_requests' => $successful,
                            'failed_requests' => $failed,
                            'total_tokens' => $group->sum(fn ($g) => ((int) $g->prompt_tokens) + ((int) $g->completion_tokens)),
                            'prompt_tokens' => $group->sum(fn ($g) => (int) $g->prompt_tokens),
                            'completion_tokens' => $group->sum(fn ($g) => (int) $g->completion_tokens),
                            'total_cost_usd' => $group->sum(fn ($g) => (float) $g->cost_usd),
                        ]
                    );

                    $count++;
                }
            });

        $this->components->info("Recalculated {$count} aggregate bucket(s).");

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
