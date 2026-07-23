<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Models\GlintAlertRule;

final class ToggleAlertRuleAction
{
    public function handle(GlintAlertRule $rule): void
    {
        rescue(fn () => $rule->update(['enabled' => ! $rule->enabled]));
    }
}
