<?php

declare(strict_types=1);

use Cybernerdie\Glint\Console\Commands\DispatchAlertsCommand;
use Cybernerdie\Glint\Models\GlintAlertEvent;
use Illuminate\Console\Command;

it('has the correct signature', function () {
    expect((new DispatchAlertsCommand)->getName())->toBe('glint:dispatch-alerts');
});

it('exits successfully and produces output', function () {
    $this->artisan('glint:dispatch-alerts')
        ->expectsOutputToContain('Evaluating alert rules')
        ->assertExitCode(Command::SUCCESS);
});

it('does not create alert events when no rules are configured', function () {
    $this->artisan('glint:dispatch-alerts');

    expect(GlintAlertEvent::count())->toBe(0);
});
