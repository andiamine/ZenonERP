<?php

namespace Modules\DummyDep\Hooks;

use Modules\Dummy\Contracts\Hooks\DummyItemConfirming;

/**
 * Cross-module veto filter (§6): aborts Dummy's confirm action — the fixture twin of
 * the M2 EnforceCreditLimit example. Registered with priority 10 to run early.
 */
final class VetoWhenNameForbidden
{
    public function __invoke(DummyItemConfirming $payload): void
    {
        if ($payload->name === 'forbidden') {
            $payload->veto('Dummy item name is forbidden.', 'dummydep.forbidden_name');
        }
    }
}
