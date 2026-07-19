<?php

namespace Modules\Dummy\Contracts\Hooks;

use App\Foundation\Hooks\Concerns\Vetoable;

/**
 * Vetoable payload (§6): filters may veto the confirmation before it commits.
 * Fixture twin of the M2 SalesOrderConfirming contract.
 */
final class DummyItemConfirming
{
    use Vetoable;

    public function __construct(public readonly string $name) {}
}
