<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintGeneration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class GenerationListQuery
{
    public function __construct(
        private readonly ?Carbon $fromDt,
        private readonly ?Carbon $toDt,
        private readonly string $provider = '',
        private readonly string $model = '',
        private readonly string $status = '',
    ) {}

    /** @return LengthAwarePaginator<GlintGeneration> */
    public function get(): LengthAwarePaginator
    {
        $query = GlintGeneration::query()->latest('started_at');

        if ($this->provider !== '') {
            $query->where('provider', $this->provider);
        }

        if ($this->model !== '') {
            $query->where('model', $this->model);
        }

        if ($this->status !== '' && in_array($this->status, ['success', 'error', 'pending'], true)) {
            $query->where('status', $this->status);
        }

        if ($this->fromDt !== null) {
            $query->where('started_at', '>=', $this->fromDt);
        }

        if ($this->toDt !== null) {
            $query->where('started_at', '<=', $this->toDt);
        }

        return $query->paginate(25)->withQueryString();
    }
}
