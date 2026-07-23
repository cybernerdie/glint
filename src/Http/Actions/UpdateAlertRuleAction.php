<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Http\Requests\StoreAlertRuleRequest;
use Cybernerdie\Glint\Models\GlintAlertRule;

final class UpdateAlertRuleAction
{
    public function handle(GlintAlertRule $rule, StoreAlertRuleRequest $request): void
    {
        rescue(fn () => $rule->update($request->toPayload()));
    }
}
