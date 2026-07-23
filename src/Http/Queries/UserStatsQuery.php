<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Carbon;

final readonly class UserStatsQuery
{
    public function __construct(
        private string $userId,
        private ?Carbon $fromDt,
        private ?Carbon $toDt,
    ) {}

    /** @return array{trace_count: int, total_cost: float, avg_duration: int, total_tokens: int, error_count: int} */
    public function get(): array
    {
        $row = GlintTrace::query()
            ->selectRaw(
                'COUNT(DISTINCT glint_traces.id) as trace_count, '
                .'SUM(glint_generations.cost_usd) as total_cost, '
                .'AVG(glint_traces.duration_ms) as avg_duration, '
                .'SUM(glint_generations.total_tokens) as total_tokens, '
                .'COUNT(CASE WHEN glint_traces.status = ? THEN 1 END) as error_count',
                [RecordStatus::Error->value]
            )
            ->leftJoin('glint_generations', 'glint_generations.trace_id', '=', 'glint_traces.id')
            ->where('glint_traces.user_id', $this->userId)
            ->when($this->fromDt, fn ($q) => $q->where('glint_traces.started_at', '>=', $this->fromDt))
            ->when($this->toDt, fn ($q) => $q->where('glint_traces.started_at', '<=', $this->toDt))
            ->first();

        $attrs = $row ? $row->getAttributes() : [];

        return [
            'trace_count' => isset($attrs['trace_count']) && is_numeric($attrs['trace_count']) ? (int) $attrs['trace_count'] : 0,
            'total_cost' => isset($attrs['total_cost']) && is_numeric($attrs['total_cost']) ? (float) $attrs['total_cost'] : 0.0,
            'avg_duration' => isset($attrs['avg_duration']) && is_numeric($attrs['avg_duration']) ? (int) $attrs['avg_duration'] : 0,
            'total_tokens' => isset($attrs['total_tokens']) && is_numeric($attrs['total_tokens']) ? (int) $attrs['total_tokens'] : 0,
            'error_count' => isset($attrs['error_count']) && is_numeric($attrs['error_count']) ? (int) $attrs['error_count'] : 0,
        ];
    }

    /** @return array{trace_count: int, total_cost: float, avg_duration: int, total_tokens: int, error_count: int} */
    public static function empty(): array
    {
        return [
            'trace_count' => 0,
            'total_cost' => 0.0,
            'avg_duration' => 0,
            'total_tokens' => 0,
            'error_count' => 0,
        ];
    }
}
