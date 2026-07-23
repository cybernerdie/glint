<?php

declare(strict_types=1);

namespace Cybernerdie\Glint\Http\Actions;

use Cybernerdie\Glint\Http\Requests\StoreAlertRuleRequest;
use Cybernerdie\Glint\Models\GlintAlertRule;

final class StoreAlertRuleAction
{
    public function handle(StoreAlertRuleRequest $request): void
    {
        rescue(fn () => GlintAlertRule::create($request->toPayload()));
    }
}
