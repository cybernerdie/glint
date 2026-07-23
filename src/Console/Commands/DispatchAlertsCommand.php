<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Console\Commands;

use Cybernerdie\Glint\Alerts\AlertDispatcher;
use Illuminate\Console\Command;

final class DispatchAlertsCommand extends Command
{
    protected $signature = 'glint:dispatch-alerts';

    protected $description = 'Evaluate all active alert rules and dispatch any triggered notifications';

    public function handle(AlertDispatcher $dispatcher): int
    {
        $this->components->info('Evaluating alert rules...');

        $dispatcher->evaluate();

        $this->components->info('Done.');

        return self::SUCCESS;
    }
}
