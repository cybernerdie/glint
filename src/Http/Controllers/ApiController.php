<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Controllers;

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintGeneration;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Http\JsonResponse;

final class ApiController
{
    public function metrics(): JsonResponse
    {
        $since = now()->subHours(24);

        $data = rescue(function () use ($since) {
            $row = GlintGeneration::query()
                ->where('started_at', '>=', $since)
                ->selectRaw(
                    'SUM(total_tokens) as total_tokens, SUM(cost_usd) as total_cost, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as error_count',
                    [RecordStatus::Error->value]
                )
                ->first();

            $attrs = $row ? $row->getAttributes() : [];

            return [
                'total_requests' => GlintTrace::query()->where('started_at', '>=', $since)->count(),
                'total_tokens' => isset($attrs['total_tokens']) && is_numeric($attrs['total_tokens']) ? (int) $attrs['total_tokens'] : 0,
                'total_cost' => isset($attrs['total_cost']) && is_numeric($attrs['total_cost']) ? (float) $attrs['total_cost'] : 0.0,
                'error_count' => isset($attrs['error_count']) && is_numeric($attrs['error_count']) ? (int) $attrs['error_count'] : 0,
            ];
        }, [
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0.0,
            'error_count' => 0,
        ]);

        return response()->json($data);
    }
}
