<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Queries;

use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final readonly class TraceListQuery
{
    public function __construct(
        private ?Carbon $fromDt,
        private ?Carbon $toDt,
        private string $search = '',
        private string $status = '',
        private string $userId = '',
    ) {}

    /** @return LengthAwarePaginator<int, GlintTrace> */
    public function get(): LengthAwarePaginator
    {
        $query = GlintTrace::query()->latest('started_at');

        if ($this->search !== '') {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        if (in_array($this->status, ['success', 'error', 'pending'], true)) {
            $query->where('status', $this->status);
        }

        if ($this->userId !== '') {
            $query->where('user_id', $this->userId);
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
