# Alerts

Glint evaluates alert rules every five minutes and fires a `GlintAlertTriggered` event when a threshold is crossed.

## Alert types

| Type | What it watches |
|------|----------------|
| `cost_threshold` | Total cost (USD) in a period exceeds a limit |
| `error_rate` | Percentage of failed generations exceeds a threshold |
| `latency_spike` | Average generation duration (ms) exceeds a threshold |
| `token_spike` | Total tokens consumed in a period exceeds a limit |

## Creating alert rules

Alert rules are stored in the `glint_alert_rules` table. Create them programmatically in a seeder or migration:

```php
use Cybernerdie\Glint\Models\GlintAlertRule;
use Cybernerdie\Glint\Enums\AlertRuleType;

GlintAlertRule::create([
    'name'             => 'Daily cost over $50',
    'type'             => AlertRuleType::CostThreshold,
    'enabled'          => true,
    'threshold_config' => [
        'threshold' => 50.0,
        'period'    => 'day',       // hour | day | week | month
        'provider'  => 'openai',   // optional — omit to watch all providers
    ],
    'channels'         => ['email'],
    'cooldown_minutes' => 60,
]);
```

## Receiving alerts

Listen to `GlintAlertTriggered` in your `AppServiceProvider` or a dedicated event listener:

```php
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

Event::listen(GlintAlertTriggered::class, function (GlintAlertTriggered $event) {
    Notification::route('mail', 'ops@example.com')
        ->notify(new LlmCostAlertNotification($event));
});
```

The event carries:

```php
$event->type;          // AlertRuleType enum
$event->threshold;     // float — the configured limit
$event->currentValue;  // float — the current measured value
$event->period;        // 'day' | 'hour' | 'week' | 'month'
$event->channel;       // string — first channel from the rule's channels array
$event->alertRuleId;   // int
$event->alertEventId;  // int — the GlintAlertEvent that was created
```

## Cooldown

`cooldown_minutes` prevents the same rule from firing repeatedly. Once an alert fires, the rule will not fire again until the cooldown period has elapsed.

## Alert history

All fired alerts are stored in `glint_alert_events` and visible in the dashboard. Retention is controlled by `GLINT_RETENTION_ALERTS` (default: 90 days).
