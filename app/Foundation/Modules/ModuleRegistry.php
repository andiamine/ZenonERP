<?php

namespace App\Foundation\Modules;

use App\Foundation\Modules\Exceptions\ModuleNotInstalledException;
use App\Foundation\Modules\Models\InstalledModule;
use App\Foundation\Modules\Models\TenantModule;
use App\Models\Tenant;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;

/**
 * The single enablement authority (CLAUDE.md §13 risk #1): route middleware — and, from
 * Phase 6, the hook bus and the tenant-gated listener decorator — consult ONLY this class.
 *
 * Caching: per-request in-memory memos only (container-scoped binding). A shared cache
 * store is deliberately NOT used: PrefixCacheTenancyBootstrapper rescopes the entire
 * cache per tenant context, so one logical entry would fragment across prefixes and
 * flushTenantCache() could never reliably clear it. `zenon.registry_cache_ttl` stays
 * reserved for a later shared-store design that solves prefix scoping explicitly.
 */
final class ModuleRegistry
{
    /** @var array<string, ManifestData>|null */
    private ?array $discovered = null;

    /** @var array<string, ManifestData>|null */
    private ?array $installed = null;

    /** @var array<string, list<string>> */
    private array $enabledByTenant = [];

    public function __construct(private readonly RepositoryInterface $modules) {}

    /**
     * Every module on disk carrying a zenon block, keyed by manifest alias.
     * Manifests without a zenon block are skipped (not ours to manage).
     *
     * @return array<string, ManifestData>
     */
    public function discovered(): array
    {
        if ($this->discovered !== null) {
            return $this->discovered;
        }

        $discovered = [];

        /** @var Module $module */
        foreach ($this->modules->all() as $module) {
            $manifest = ManifestData::fromNwidartModule($module);

            if ($manifest->hasZenonBlock() && $manifest->alias !== '') {
                $discovered[$manifest->alias] = $manifest;
            }
        }

        return $this->discovered = $discovered;
    }

    /**
     * Installed = central `modules` rows ∩ discovered manifests, keyed by alias.
     *
     * @return array<string, ManifestData>
     */
    public function installed(): array
    {
        if ($this->installed !== null) {
            return $this->installed;
        }

        $discovered = $this->discovered();

        return $this->installed = InstalledModule::query()
            ->pluck('alias')
            ->intersect(array_keys($discovered))
            ->mapWithKeys(fn (string $alias) => [$alias => $discovered[$alias]])
            ->all();
    }

    public function isInstalled(string $alias): bool
    {
        return array_key_exists($alias, $this->installed());
    }

    /**
     * Manifest of an INSTALLED module.
     *
     * @throws ModuleNotInstalledException
     */
    public function manifest(string $alias): ManifestData
    {
        return $this->installed()[$alias] ?? throw ModuleNotInstalledException::forAlias($alias);
    }

    /**
     * @return list<string> enabled module aliases (∩ installed) for the tenant
     */
    public function enabledFor(Tenant|string $tenant): array
    {
        $tenantId = is_string($tenant) ? $tenant : (string) $tenant->getTenantKey();

        return $this->enabledByTenant[$tenantId] ??= array_values(TenantModule::query()
            ->where('tenant_id', $tenantId)
            ->where('enabled', true)
            ->pluck('module')
            ->map(fn ($module) => (string) $module)
            ->intersect(array_keys($this->installed()))
            ->all());
    }

    public function isEnabledFor(string $alias, Tenant|string $tenant): bool
    {
        return in_array($alias, $this->enabledFor($tenant), true);
    }

    /**
     * Third-party remotes (CLAUDE.md §7) ready for the SPA loader: enabled-for-tenant
     * manifests that declare a frontend.remote. Deliberately NO filesystem existence
     * check (ModuleAssetController 404s on a missing file, which is enough) and NO
     * platform filtering here — the loader owns compatibility refusal, so `platform`
     * is passed through verbatim for it to judge (architectural decision D8).
     *
     * @return list<array{id: string, url: string, platform: string}>
     */
    public function remoteModulesFor(Tenant|string $tenant): array
    {
        $remotes = [];

        foreach ($this->enabledFor($tenant) as $alias) {
            $manifest = $this->manifest($alias);

            if ($manifest->frontendRemote === null) {
                continue;
            }

            $folder = basename(str_replace('\\', '/', $manifest->path));
            $remote = str_replace('\\', '/', $manifest->frontendRemote);

            $remotes[] = [
                'id' => $alias,
                'url' => '/modules/thirdparty/'.$folder.'/'.$remote,
                'platform' => $manifest->platform,
            ];
        }

        return $remotes;
    }

    public function isEnabledForCurrentTenant(string $alias): bool
    {
        if (! tenancy()->initialized || tenant() === null) {
            return false; // no tenant context → a module is never enabled
        }

        return $this->isEnabledFor($alias, (string) tenant()->getTenantKey());
    }

    public function flushTenantCache(Tenant|string|null $tenant = null): void
    {
        if ($tenant === null) {
            $this->enabledByTenant = [];

            return;
        }

        unset($this->enabledByTenant[is_string($tenant) ? $tenant : (string) $tenant->getTenantKey()]);
    }

    /** Full reset — after install/uninstall/upgrade mutations. */
    public function flush(): void
    {
        $this->discovered = null;
        $this->installed = null;
        $this->enabledByTenant = [];
    }
}
