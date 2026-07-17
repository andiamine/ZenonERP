<?php

namespace App\Foundation\Modules;

use App\Foundation\Modules\Contracts\ModuleLifecycle;
use App\Models\Tenant;

/** No-op lifecycle — modules override only the hooks they need. */
abstract class BaseModuleLifecycle implements ModuleLifecycle
{
    public function installed(): void {}

    public function enabling(Tenant $tenant): void {}

    public function enabled(Tenant $tenant): void {}

    public function disabled(Tenant $tenant): void {}

    public function purging(Tenant $tenant): void {}
}
