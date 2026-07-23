<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Requests;

use Cybernerdie\Glint\Enums\AggregatePeriod;
use Cybernerdie\Glint\Enums\AlertRuleScope;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_column(AlertRuleType::cases(), 'value'))],
            'threshold' => ['required', 'numeric', 'min:0'],
            'period' => ['required', Rule::in(array_column(AggregatePeriod::cases(), 'value'))],
            'scope' => ['required', Rule::in(array_column(AlertRuleScope::cases(), 'value'))],
            'scope_id' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(['mail', 'webhook', 'slack', 'log'])],
            'mail_to' => [
                Rule::requiredIf(fn () => in_array('mail', (array) $this->input('channels', []), true)),
                'nullable', 'email', 'max:255',
            ],
            'webhook_url' => [
                Rule::requiredIf(fn () => in_array('webhook', (array) $this->input('channels', []), true)),
                'nullable', 'url', 'max:500',
            ],
            'cooldown_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'enabled' => ['boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function toPayload(): array
    {
        $thresholdRaw = $this->input('threshold', 0);
        $threshold = is_numeric($thresholdRaw) ? (float) $thresholdRaw : 0.0;
        $rawChannels = $this->input('channels', []);
        $channels = array_values(array_map('strval', array_filter(is_array($rawChannels) ? $rawChannels : [], 'is_scalar')));

        return [
            'name' => $this->string('name')->toString(),
            'type' => $this->string('type')->toString(),
            'scope' => $this->string('scope')->toString(),
            'scope_id' => $this->string('scope_id')->toString() ?: null,
            'threshold_config' => [
                'threshold' => $threshold,
                'period' => $this->string('period')->toString(),
                'provider' => $this->string('provider')->toString() ?: null,
            ],
            'channels' => $channels,
            'mail_to' => in_array('mail', $channels, true) ? ($this->string('mail_to')->toString() ?: null) : null,
            'webhook_url' => in_array('webhook', $channels, true) ? ($this->string('webhook_url')->toString() ?: null) : null,
            'cooldown_minutes' => $this->integer('cooldown_minutes'),
            'enabled' => (bool) $this->input('enabled', true),
        ];
    }
}
