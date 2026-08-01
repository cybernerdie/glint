# Alerts

Glint evaluates alert rules every five minutes and fires a `GlintAlertTriggered` event when a threshold is crossed.

## Alert types

| Type | What it watches |
|------|----------------|
| Cost Threshold | Total cost (USD) in a period exceeds a limit |
| Error Rate | Percentage of failed generations exceeds a threshold |
| Latency Spike | Average generation duration (ms) exceeds a threshold |
| Token Spike | Total tokens consumed in a period exceeds a limit |

## Creating alert rules

Open the Glint dashboard and click **Alerts** in the sidebar, then **New Rule**.

### Fields

**Name** — A label for the rule. Shown in the rules list and in notifications.

**Alert Type** — What metric to watch. The threshold unit changes to match:

| Type | Threshold unit |
|------|---------------|
| Cost Threshold | USD (e.g. `10.00` = alert when cost exceeds $10) |
| Error Rate | Percentage (e.g. `5` = alert when error rate exceeds 5%) |
| Latency Spike | Milliseconds (e.g. `2000` = alert when avg latency exceeds 2,000 ms) |
| Token Spike | Token count (e.g. `500000` = alert when usage exceeds 500K tokens) |

**Threshold Value** — The numeric limit. The rule fires when the current value meets or exceeds this number.

**Evaluation Period** — Which aggregate window to check: Hour, Day, Week, or Month. Glint compares the threshold against the most recent aggregate for that window.

**Provider Filter** — Optionally narrow the rule to a single provider. Leave blank to watch all providers.

**Notification Channels** — Where to send the alert. Select one or more:

| Channel | Requires |
|---------|---------|
| Log | Nothing — always available |
| Mail | `illuminate/mail` configured; enter a recipient email |
| Slack | `laravel/slack-notification-channel` installed |
| Webhook | Any reachable URL; Glint POSTs a JSON payload |

**Cooldown** — Minimum minutes between repeated firings of the same rule. Prevents alert storms during a sustained spike. Default: 60 minutes.

**Status** — Enable or disable the rule. Disabled rules are stored but never evaluated.

## Managing rules

The Alerts index lists all rules with their type, threshold, provider filter, channels, and enabled status. From there you can:

- **Enable / Disable** — Toggle a rule without deleting it.
- **Delete** — Remove the rule and its event history permanently.

## Alert events log

Every time a rule fires, Glint creates a `GlintAlertEvent` record. The bottom panel of the Alerts index shows the last 50 events with the measured value, threshold, channel used, and whether delivery succeeded or failed.

Retention is controlled by `GLINT_RETENTION_ALERTS` (default: 90 days).

## Receiving alerts in code

You can also react to alerts programmatically by listening to `GlintAlertTriggered`:

```php
// AppServiceProvider::boot()
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Illuminate\Support\Facades\Event;

Event::listen(GlintAlertTriggered::class, function (GlintAlertTriggered $event) {
    // $event->type          AlertRuleType enum
    // $event->threshold     float — the configured limit
    // $event->currentValue  float — the measured value that triggered the rule
    // $event->period        'hour' | 'day' | 'week' | 'month'
    // $event->channel       string — the channel Glint attempted to deliver to
    // $event->alertRuleId   int
    // $event->alertEventId  int
});
```

