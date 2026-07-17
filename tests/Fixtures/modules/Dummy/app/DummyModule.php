<?php

namespace Modules\Dummy;

use App\Foundation\Modules\BaseModuleLifecycle;
use App\Models\Tenant;

class DummyModule extends BaseModuleLifecycle
{
    /** @var list<string> lifecycle call log, asserted (and reset) by tests */
    public static array $log = [];

    public function installed(): void
    {
        self::$log[] = 'installed';
    }

    public function enabling(Tenant $tenant): void
    {
        self::$log[] = 'enabling:'.$tenant->getTenantKey();
    }

    public function enabled(Tenant $tenant): void
    {
        self::$log[] = 'enabled:'.$tenant->getTenantKey();
    }

    public function disabled(Tenant $tenant): void
    {
        self::$log[] = 'disabled:'.$tenant->getTenantKey();
    }

    public function purging(Tenant $tenant): void
    {
        self::$log[] = 'purging:'.$tenant->getTenantKey();
    }
}
