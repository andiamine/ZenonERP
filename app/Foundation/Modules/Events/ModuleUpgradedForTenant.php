<?php

namespace App\Foundation\Modules\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Per-tenant: fired after one tenant successfully converges on the new version. */
final readonly class ModuleUpgradedForTenant
{
    use Dispatchable;

    public function __construct(
        public string $alias,
        public string $tenantId,
        public string $version,
    ) {}
}
