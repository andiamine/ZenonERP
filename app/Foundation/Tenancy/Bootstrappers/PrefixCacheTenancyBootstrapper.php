<?php

namespace App\Foundation\Tenancy\Bootstrappers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Per-tenant cache scoping via key prefix (mirrors v4's PrefixCacheTenancyBootstrapper —
 * v3 only ships a tags-based bootstrapper, which the non-taggable `database` store cannot support).
 *
 * The store itself stays CENTRAL: cache.stores.database.connection is pinned to the central
 * connection via DB_CACHE_CONNECTION, so tenant DBs never need cache tables.
 *
 * Limitation: with a prefix on a DB store, per-tenant cache flush means
 * DELETE WHERE key LIKE prefix% — tooling for that is deferred until needed.
 */
final class PrefixCacheTenancyBootstrapper implements TenancyBootstrapper
{
    private ?string $originalPrefix = null;

    public function __construct(
        private readonly Application $app,
        private readonly ConfigRepository $config,
    ) {}

    public function bootstrap(Tenant $tenant): void
    {
        $this->originalPrefix = (string) $this->config->get('cache.prefix');
        $prefixBase = (string) $this->config->get('tenancy.cache.prefix_base', 'tenant_');

        $this->setPrefix($this->originalPrefix.$prefixBase.$tenant->getTenantKey().':');
    }

    public function revert(): void
    {
        if ($this->originalPrefix !== null) {
            $this->setPrefix($this->originalPrefix);
            $this->originalPrefix = null;
        }
    }

    private function setPrefix(string $prefix): void
    {
        $this->config->set('cache.prefix', $prefix);

        // Resolve the manager fresh — forgetInstance() below invalidates any captured instance
        // between initialize/end cycles, so a constructor-injected manager would go stale.
        $this->app->make('cache')->forgetDriver((string) $this->config->get('cache.default'));

        // Drop bindings/facades that captured a repository built with the old prefix.
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
        Facade::clearResolvedInstance('cache');
        Facade::clearResolvedInstance('cache.store');
    }
}
