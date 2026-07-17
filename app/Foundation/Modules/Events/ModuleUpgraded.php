<?php

namespace App\Foundation\Modules\Events;

use Illuminate\Foundation\Events\Dispatchable;

/** Central: fired once when an upgrade batch is dispatched. */
final readonly class ModuleUpgraded
{
    use Dispatchable;

    public function __construct(
        public string $alias,
        public string $fromVersion,
        public string $toVersion,
    ) {}
}
