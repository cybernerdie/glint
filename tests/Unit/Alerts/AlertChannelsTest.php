<?php

declare(strict_types=1);

use Cybernerdie\Glint\Alerts\Channels\LogChannel;
use Cybernerdie\Glint\Alerts\Channels\MailChannel;
use Cybernerdie\Glint\Alerts\Channels\SlackChannel;
use Cybernerdie\Glint\Alerts\Channels\WebhookChannel;
use Cybernerdie\Glint\Enums\AlertRuleType;
use Cybernerdie\Glint\Events\GlintAlertTriggered;
use Cybernerdie\Glint\Listeners\SendAlertNotification;
use Cybernerdie\Glint\Mail\GlintAlertMail;
use Cybernerdie\Glint\Models\GlintAlertRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

function makeEvent(GlintAlertRule $rule, string $channel = 'log'): GlintAlertTriggered
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

    app(LogChannel::class)->handle(makeEvent($rule), $rule);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with(Mockery::on(fn (string $msg) => str_contains($msg, 'High Cost')), Mockery::any());
});

// ---------------------------------------------------------------------------
// MailChannel
// ---------------------------------------------------------------------------

it('MailChannel sends a GlintAlertMail when mail_to is set', function () {
    Mail::fake();

    $rule = GlintAlertRule::factory()->create(['mail_to' => 'test@example.com']);

    app(MailChannel::class)->handle(makeEvent($rule, 'mail'), $rule);

    Mail::assertSent(GlintAlertMail::class, fn (GlintAlertMail $mail) => $mail->hasTo('test@example.com'));
});

it('MailChannel skips when mail_to is empty', function () {
    Mail::fake();

    $rule = GlintAlertRule::factory()->create(['mail_to' => null]);

    app(MailChannel::class)->handle(makeEvent($rule, 'mail'), $rule);

    Mail::assertNothingSent();
});

// ---------------------------------------------------------------------------
// WebhookChannel
// ---------------------------------------------------------------------------

it('WebhookChannel posts payload to webhook_url', function () {
    Http::fake(['https://example.com/hook' => Http::response('', 200)]);

    $rule = GlintAlertRule::factory()->create(['webhook_url' => 'https://example.com/hook']);
    $event = makeEvent($rule, 'webhook');

    app(WebhookChannel::class)->handle($event, $rule);

    Http::assertSent(fn ($request) => $request->url() === 'https://example.com/hook' &&
        $request['type'] === 'cost_threshold' &&
        $request['current_value'] === 25.0 &&
        isset($request['triggered_at'])
    );
});

it('WebhookChannel skips when webhook_url is empty', function () {
    Http::fake();

    $rule = GlintAlertRule::factory()->create(['webhook_url' => null]);

    app(WebhookChannel::class)->handle(makeEvent($rule, 'webhook'), $rule);

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// SlackChannel
// ---------------------------------------------------------------------------

it('SlackChannel posts blocks payload to slack_webhook_url', function () {
    Http::fake(['https://hooks.slack.com/test' => Http::response('ok', 200)]);

    $rule = GlintAlertRule::factory()->create(['slack_webhook_url' => 'https://hooks.slack.com/test']);

    app(SlackChannel::class)->handle(makeEvent($rule, 'slack'), $rule);

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/test' &&
        isset($request['blocks'])
    );
});

it('SlackChannel skips when slack_webhook_url is empty', function () {
    Http::fake();

    $rule = GlintAlertRule::factory()->create(['slack_webhook_url' => null]);

    app(SlackChannel::class)->handle(makeEvent($rule, 'slack'), $rule);

    Http::assertNothingSent();
});

// ---------------------------------------------------------------------------
// SendAlertNotification listener — routing verified via channel side-effects
// ---------------------------------------------------------------------------

it('SendAlertNotification routes log channel and logs a warning', function () {
    Log::spy();

    $rule = GlintAlertRule::factory()->create(['name' => 'Test Rule']);

    app(SendAlertNotification::class)->handle(makeEvent($rule, 'log'));

    Log::shouldHaveReceived('warning')->once();
});

it('SendAlertNotification routes mail channel and sends a mail', function () {
    Mail::fake();

    $rule = GlintAlertRule::factory()->create(['mail_to' => 'test@example.com']);

    app(SendAlertNotification::class)->handle(makeEvent($rule, 'mail'));

    Mail::assertSent(GlintAlertMail::class);
});

it('SendAlertNotification routes webhook channel and posts to URL', function () {
    Http::fake(['https://example.com/hook' => Http::response('', 200)]);

    $rule = GlintAlertRule::factory()->create(['webhook_url' => 'https://example.com/hook']);

    app(SendAlertNotification::class)->handle(makeEvent($rule, 'webhook'));

    Http::assertSentCount(1);
});

it('SendAlertNotification routes slack channel and posts to URL', function () {
    Http::fake(['https://hooks.slack.com/test' => Http::response('ok', 200)]);

    $rule = GlintAlertRule::factory()->create(['slack_webhook_url' => 'https://hooks.slack.com/test']);

    app(SendAlertNotification::class)->handle(makeEvent($rule, 'slack'));

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
        channel: 'log',
        alertEventId: 1,
    );

    app(SendAlertNotification::class)->handle($event);

    Log::shouldNotHaveReceived('warning');
    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('SendAlertNotification does nothing for an unknown channel', function () {
    Log::spy();
    Mail::fake();
    Http::fake();

    $rule = GlintAlertRule::factory()->create();

    app(SendAlertNotification::class)->handle(makeEvent($rule, 'unknown'));

    Log::shouldNotHaveReceived('warning');
    Mail::assertNothingSent();
    Http::assertNothingSent();
});
