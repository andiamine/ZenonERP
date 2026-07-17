<?php

namespace App\Foundation\Modules\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class ModuleUninstalled
{
    use Dispatchable;

    public function __construct(
        public string $alias,
    ) {}
}
