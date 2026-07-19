<?php

namespace Modules\Dummy\Contracts\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Public event contract (§6): "a dummy item was confirmed" — cross-module listeners
 * subscribe via the Extend API. Fixture twin of the M2 SalesOrderConfirmed contract.
 */
final readonly class DummyItemConfirmed
{
    use Dispatchable;

    public function __construct(public string $name) {}
}
