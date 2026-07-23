<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Models\GlintAlertRule;

final class ToggleAlertRuleAction
{
    public function handle(string $alertRuleId): void
    {
        $rule = rescue(fn () => GlintAlertRule::query()->find($alertRuleId), null);

        if ($rule === null) {
            abort(404);
        }

        rescue(fn () => $rule->update(['enabled' => ! $rule->enabled]));
    }
}
