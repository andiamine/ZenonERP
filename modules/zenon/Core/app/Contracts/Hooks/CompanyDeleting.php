<?php

namespace Modules\Core\Contracts\Hooks;

use App\Foundation\Hooks\Concerns\Vetoable;

/**
 * Vetoable payload (§6): filters may veto a company delete before it commits.
 * Production analogue of the M2 SalesOrderConfirming contract (fixture twin:
 * DummyItemConfirming).
 */
final class CompanyDeleting
{
    use Vetoable;

    public function __construct(public readonly int $companyId, public readonly string $code, public readonly string $name) {}
}
