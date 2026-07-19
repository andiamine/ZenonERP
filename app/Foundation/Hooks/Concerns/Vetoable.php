<?php

namespace App\Foundation\Hooks\Concerns;

use App\Foundation\Hooks\ActionVetoedException;

/**
 * Mixin for "…ing" hook payloads whose action a filter may abort (§6, e.g. the M2
 * SalesOrderConfirming contract). veto() throws immediately rather than marking state
 * for the emitter to poll: remaining filters are short-circuited and the emitter needs
 * no cooperation beyond calling HookBus::filter() before committing the action.
 */
trait Vetoable
{
    public function veto(string $reason, ?string $code = null): never
    {
        throw new ActionVetoedException($reason, $code);
    }
}
