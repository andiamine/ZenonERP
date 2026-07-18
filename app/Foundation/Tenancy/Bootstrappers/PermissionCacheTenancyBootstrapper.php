<?php

namespace App\Foundation\Tenancy\Bootstrappers;

use Illuminate\Contracts\Foundation\Application;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * PermissionRegistrar (singleton) holds a cache Repository built with the prefix current
 * at construction plus an in-memory permissions collection. initializeCache() re-resolves
 * the repository from its CacheManager — whose driver PrefixCacheTenancyBootstrapper just
 * forgot — and clears the in-memory collection, so permission lookups hit the new
 * tenant's prefixed keys. MUST be ordered after PrefixCacheTenancyBootstrapper in
 * tenancy.bootstrappers (revert runs in the same order).
 */
final class PermissionCacheTenancyBootstrapper implements TenancyBootstrapper
{
    public function __construct(private readonly Application $app) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->reset();
    }

    public function revert(): void
    {
        $this->reset();
    }

    private function reset(): void
    {
        $this->app->make(PermissionRegistrar::class)->initializeCache();
    }
}
