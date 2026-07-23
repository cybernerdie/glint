<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Models\GlintAlertRule;

final class DestroyAlertRuleAction
{
    public function handle(string $alertRuleId): void
    {
        $rule = rescue(fn () => GlintAlertRule::query()->find($alertRuleId), null);

        rescue(fn () => $rule?->delete());
    }
}
