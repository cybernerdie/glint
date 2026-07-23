<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Mail;

use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class GlintAlertMail extends Mailable
{
    public function __construct(
        public readonly GlintAlertTriggered $event,
        public readonly GlintAlertRule $rule,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Glint Alert: {$this->rule->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'glint::emails.alert-triggered',
            with: [
                'ruleName' => $this->rule->name,
                'typeLabel' => $this->typeLabel(),
                'currentValue' => $this->formatValue($this->event->currentValue),
                'threshold' => $this->formatValue($this->event->threshold),
                'period' => $this->event->period,
            ],
        );
    }

    private function typeLabel(): string
    {
        return match ($this->event->type) {
            AlertRuleType::CostThreshold => 'Cost Threshold',
            AlertRuleType::ErrorRate => 'Error Rate',
            AlertRuleType::LatencySpike => 'Latency Spike',
            AlertRuleType::TokenSpike => 'Token Spike',
        };
    }

    private function formatValue(float $value): string
    {
        return match ($this->event->type) {
            AlertRuleType::CostThreshold => '$'.number_format($value, 4),
            AlertRuleType::ErrorRate => number_format($value, 1).'%',
            AlertRuleType::LatencySpike => number_format($value).'ms',
            AlertRuleType::TokenSpike => number_format($value).' tokens',
        };
    }
}
