<?php

declare(strict_types=1);

use Cybernerdie\Glint\Alerts\Channels\LogChannel;
use Cybernerdie\Glint\Alerts\Channels\SlackChannel;
use Cybernerdie\Glint\Alerts\Channels\WebhookChannel;
use Cybernerdie\Glint\Enums\AlertChannel;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Listeners\SendAlertNotification;
use Cybernerdie\Glint\Mail\GlintAlertMail;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Cybernerdie\Glint\Notifications\GlintAlertNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

function makeEvent(GlintAlertRule $rule, AlertChannel $channel = AlertChannel::Log): GlintAlertTriggered
{
    return new GlintAlertTriggered(
        alertRuleId: (int) $rule->id,
        type: AlertRuleType::CostThreshold,
        threshold: 10.0,
        currentValue: 25.0,
        period: 'day',
        channel: $channel,
        alertEventId: 1,
    );
}

// ---------------------------------------------------------------------------
// LogChannel
// ---------------------------------------------------------------------------

it('LogChannel logs a warning with rule name and context', function () {
    Log::spy();

    $rule = GlintAlertRule::factory()->create(['name' => 'High Cost']);

    app(LogChannel::class)->send($rule, new GlintAlertNotification(makeEvent($rule)));

    Log::shouldHaveReceived('warning')
        ->once()
        ->with(Mockery::on(fn (string $msg) => str_contains($msg, 'High Cost')), Mockery::any());
});

// ---------------------------------------------------------------------------
// Laravel mail notification channel
// ---------------------------------------------------------------------------

it('the mail notification channel sends a GlintAlertMail when mail_to is set', function () {
    $rule = GlintAlertRule::factory()->create(['mail_to' => 'test@example.com']);
    $notification = new GlintAlertNotification(makeEvent($rule, AlertChannel::Mail));

    expect($notification->via($rule))->toBe(['mail'])
        ->and($notification->toMail($rule))->toBeInstanceOf(GlintAlertMail::class)
        ->and($notification->toMail($rule)->hasTo('test@example.com'))->toBeTrue();
});

it('the mail notification channel skips when mail_to is empty', function () {
    $rule = GlintAlertRule::factory()->create(['mail_to' => null]);

    expect((new GlintAlertNotification(makeEvent($rule, AlertChannel::Mail)))->via($rule))->toBe([]);
});

// ---------------------------------------------------------------------------
// WebhookChannel
// ---------------------------------------------------------------------------

it('WebhookChannel posts payload to webhook_url', function () {
    Http::fake(['https://example.com/hook' => Http::response('', 200)]);

    $rule = GlintAlertRule::factory()->create(['webhook_url' => 'https://example.com/hook']);
    $event = makeEvent($rule, AlertChannel::Webhook);

    app(WebhookChannel::class)->send($rule, new GlintAlertNotification($event));

    Http::assertSent(fn ($request) => $request->url() === 'https://example.com/hook' &&
        $request['type'] === 'cost_threshold' &&
        $request['current_value'] === 25.0 &&
        isset($request['triggered_at'])
    );
});

it('WebhookChannel skips when webhook_url is empty', function () {
    Http::fake();

    $rule = GlintAlertRule::factory()->create(['webhook_url' => null]);

    $rule->notify(new GlintAlertNotification(makeEvent($rule, AlertChannel::Webhook)));

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// SlackChannel
// ---------------------------------------------------------------------------

it('SlackChannel posts blocks payload to slack_webhook_url', function () {
    Http::fake(['https://hooks.slack.com/test' => Http::response('ok', 200)]);

    $rule = GlintAlertRule::factory()->create(['slack_webhook_url' => 'https://hooks.slack.com/test']);

    app(SlackChannel::class)->send($rule, new GlintAlertNotification(makeEvent($rule, AlertChannel::Slack)));

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/test' &&
        isset($request['blocks'])
    );
});

it('SlackChannel skips when slack_webhook_url is empty', function () {
    Http::fake();

    $rule = GlintAlertRule::factory()->create(['slack_webhook_url' => null]);

    $rule->notify(new GlintAlertNotification(makeEvent($rule, AlertChannel::Slack)));

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// SendAlertNotification listener — routing verified via channel side-effects
// ---------------------------------------------------------------------------

it('SendAlertNotification routes log channel and logs a warning', function () {
    Log::spy();

    $rule = GlintAlertRule::factory()->create(['name' => 'Test Rule']);

    app(SendAlertNotification::class)->handle(makeEvent($rule, AlertChannel::Log));

    Log::shouldHaveReceived('warning')->once();
});

it('SendAlertNotification routes mail channel and sends a mail', function () {
    Notification::fake();

    $rule = GlintAlertRule::factory()->create(['mail_to' => 'test@example.com']);

    app(SendAlertNotification::class)->handle(makeEvent($rule, AlertChannel::Mail));

    Notification::assertSentTo($rule, GlintAlertNotification::class, fn ($notification, $channels) => $channels === ['mail']);
});

it('SendAlertNotification routes webhook channel and posts to URL', function () {
    Http::fake(['https://example.com/hook' => Http::response('', 200)]);

    $rule = GlintAlertRule::factory()->create(['webhook_url' => 'https://example.com/hook']);

    app(SendAlertNotification::class)->handle(makeEvent($rule, AlertChannel::Webhook));

    Http::assertSentCount(1);
});

it('SendAlertNotification routes slack channel and posts to URL', function () {
    Http::fake(['https://hooks.slack.com/test' => Http::response('ok', 200)]);

    $rule = GlintAlertRule::factory()->create(['slack_webhook_url' => 'https://hooks.slack.com/test']);

    app(SendAlertNotification::class)->handle(makeEvent($rule, AlertChannel::Slack));

    Http::assertSentCount(1);
});

it('SendAlertNotification does nothing when the rule no longer exists', function () {
    Log::spy();
    Mail::fake();
    Http::fake();

    $event = new GlintAlertTriggered(
        alertRuleId: 99999,
        type: AlertRuleType::CostThreshold,
        threshold: 10.0,
        currentValue: 25.0,
        period: 'day',
        channel: AlertChannel::Log,
        alertEventId: 1,
    );

    app(SendAlertNotification::class)->handle($event);

    Log::shouldNotHaveReceived('warning');
    Mail::assertNothingSent();
    Http::assertNothingSent();
});
