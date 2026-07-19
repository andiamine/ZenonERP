<?php

namespace Modules\Core\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Public event contract (§6): "a company was deleted" — cross-module listeners subscribe
 * via the Extend API. Production analogue of the M2 SalesOrderConfirmed contract (fixture
 * twin: DummyItemConfirmed).
 */
final readonly class CompanyDeleted
{
    use Dispatchable;

    public function __construct(public int $companyId, public string $code, public string $name) {}
}
