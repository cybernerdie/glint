<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Requests;

use Cybernerdie\Glint\Http\Concerns\ResolvesDateRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

abstract class BaseFilterRequest extends FormRequest
{
    use ResolvesDateRange;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function period(): string
    {
        return $this->normalizePeriod($this->string('period')->toString());
    }

    public function fromDate(): string
    {
        return $this->string('from')->toString();
    }

    public function toDate(): string
    {
        return $this->string('to')->toString();
    }

    /** @return array{0: Carbon|null, 1: Carbon|null} */
    public function dateRange(): array
    {
        return $this->resolveDateRange($this->period(), $this->fromDate(), $this->toDate());
    }
}
