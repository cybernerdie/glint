<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Models\GlintAlertRule;

final class DestroyAlertRuleAction
{
    public function handle(GlintAlertRule $rule): void
    {
        rescue(fn () => $rule->delete());
    }
}
