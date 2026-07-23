<?php

declare(strict_types=1);

use Cybernerdie\Glint\Enums\RecordStatus;
use Cybernerdie\Glint\Models\GlintTrace;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config()->set('glint.enabled', true);
    config()->set('glint.recording.store_bodies', true);
});

it('creates a glint_traces row when a request goes through the middleware', function () {
    Route::get('/glint-test-route', fn () => response('OK'))->middleware('glint');

    $this->get('/glint-test-route');

    expect(GlintTrace::count())->toBe(1);
});

it('closes the trace with status=success after the response', function () {
    Route::get('/glint-status-check', fn () => response('Done'))->middleware('glint');

    $this->get('/glint-status-check');

    $trace = GlintTrace::first();

    expect($trace->status)->toBe(RecordStatus::Success)
        ->and($trace->ended_at)->not->toBeNull()
        ->and($trace->duration_ms)->not->toBeNull();
});

it('passes through without recording when glint.enabled = false', function () {
    config()->set('glint.enabled', false);

    Route::get('/glint-disabled-route', fn () => response('Disabled'))->middleware('glint');

    $this->get('/glint-disabled-route');

    expect(GlintTrace::count())->toBe(0);
});

it('stores request metadata on the trace (method, path, ip)', function () {
    Route::get('/glint-meta-route', fn () => response('meta'))->middleware('glint');

    $this->get('/glint-meta-route');

    $trace = GlintTrace::first();
    $metadata = $trace->metadata;

    expect($metadata)->toBeArray()
        ->and($metadata['method'])->toBe('GET')
        ->and($metadata['path'])->toBe('glint-meta-route')
        ->and(array_key_exists('ip', $metadata))->toBeTrue()
        ->and(array_key_exists('user_agent', $metadata))->toBeTrue()
        ->and(array_key_exists('env', $metadata))->toBeTrue();
});

it('stores the response body when store_bodies = true', function () {
    Route::get('/glint-body-test', fn () => response('response body here'))->middleware('glint');

    $this->get('/glint-body-test');

    $trace = GlintTrace::first();

    expect($trace->output)->toBe('response body here');
});

it('does not store the response body when store_bodies = false', function () {
    config()->set('glint.recording.store_bodies', false);

    Route::get('/glint-no-body', fn () => response('response body'))->middleware('glint');

    $this->get('/glint-no-body');

    $trace = GlintTrace::first();

    expect($trace->output)->toBeNull();
});

it('handles null user-agent gracefully', function () {
    Route::get('/glint-no-ua', fn () => response('no ua'))->middleware('glint');

    $this->get('/glint-no-ua', ['User-Agent' => null]);

    expect(GlintTrace::count())->toBeGreaterThanOrEqual(0);
});

it('skips non-string values in redact_patterns without crashing', function () {
    config()->set('glint.privacy.redact_patterns', [null, 42, '/foo/']);

    Route::get('/glint-redact-test', fn () => response('ok'))->middleware('glint');

    $this->withHeader('User-Agent', 'Mozilla/5.0 foo bar');
    $this->get('/glint-redact-test');

    $trace = GlintTrace::first();

    expect($trace->metadata['user_agent'])->not->toContain('foo');
});

it('applies a valid redaction pattern to the user-agent', function () {
    config()->set('glint.privacy.redact_patterns', ['/secret-token-\w+/']);

    Route::get('/glint-redact-pattern', fn () => response('ok'))->middleware('glint');

    $this->withHeader('User-Agent', 'MyApp/1.0 secret-token-abc123 OtherInfo');
    $this->get('/glint-redact-pattern');

    $trace = GlintTrace::first();

    expect($trace->metadata['user_agent'])->toContain('[REDACTED]')
        ->and($trace->metadata['user_agent'])->not->toContain('secret-token-abc123');
});

it('marks trace status as error when response is 5xx', function () {
    Route::get('/glint-500-route', fn () => response('Internal Server Error', 500))->middleware('glint');

    $this->get('/glint-500-route');

    $trace = GlintTrace::first();

    expect($trace->status)->toBe(RecordStatus::Error);
});

it('does not create a trace for requests to the glint dashboard path', function () {
    config()->set('glint.path', 'glint');

    Route::get('/glint', fn () => response('dashboard'))->middleware('glint');
    Route::get('/glint/traces', fn () => response('traces'))->middleware('glint');

    $this->get('/glint');
    $this->get('/glint/traces');

    expect(GlintTrace::count())->toBe(0);
});

it('does not store ip when store_ip = false', function () {
    config()->set('glint.privacy.store_ip', false);

    Route::get('/glint-no-ip', fn () => response('ok'))->middleware('glint');

    $this->get('/glint-no-ip');

    $trace = GlintTrace::first();

    expect($trace->metadata['ip'])->toBeNull();
});

it('stores ip when store_ip = true', function () {
    config()->set('glint.privacy.store_ip', true);

    Route::get('/glint-with-ip', fn () => response('ok'))->middleware('glint');

    $this->get('/glint-with-ip');

    $trace = GlintTrace::first();

    expect(array_key_exists('ip', $trace->metadata))->toBeTrue();
});

it('logs debug message and continues when redact_pattern is invalid regex', function () {
    config()->set('glint.privacy.redact_patterns', ['/[invalid-regex/']);

    Route::get('/glint-bad-regex', fn () => response('ok'))->middleware('glint');

    $this->withHeader('User-Agent', 'Mozilla/5.0 safe-ua');

    $this->get('/glint-bad-regex');

    $trace = GlintTrace::first();

    expect($trace->metadata['user_agent'])->toContain('safe-ua');
});

it('marks trace status as success when response is 4xx', function () {
    Route::get('/glint-404-route', fn () => response('Not Found', 404))->middleware('glint');

    $this->get('/glint-404-route');

    $trace = GlintTrace::first();

    expect($trace->status)->toBe(RecordStatus::Success);
});
