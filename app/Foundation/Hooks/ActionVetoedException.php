<?php

namespace App\Foundation\Hooks;

use RuntimeException;

/**
 * Raised when a hook filter vetoes the action in flight (§6): a Vetoable payload's
 * veto() throws this, the emitter lets it propagate, and ApiExceptionRenderer maps it
 * to the 422 'action_vetoed' envelope — reason as the message, vetoCode as the
 * machine-readable envelope `code`.
 */
final class ActionVetoedException extends RuntimeException
{
    public function __construct(string $reason, public readonly ?string $vetoCode = null)
    {
        parent::__construct($reason);
    }
}
