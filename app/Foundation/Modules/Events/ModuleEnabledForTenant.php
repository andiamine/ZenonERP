<?php

namespace App\Foundation\Modules\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class ModuleEnabledForTenant
{
    use Dispatchable;

    public function __construct(
        public string $alias,
        public string $tenantId,
    ) {}
}
