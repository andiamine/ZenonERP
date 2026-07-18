<?php

namespace App\Foundation\Modules;

use App\Foundation\Modules\Contracts\ModuleLifecycle;
use App\Foundation\Modules\Events\ModuleDisabledForTenant;
use App\Foundation\Modules\Events\ModuleEnabledForTenant;
use App\Foundation\Modules\Events\ModuleInstalled;
use App\Foundation\Modules\Events\ModulePurgedForTenant;
use App\Foundation\Modules\Events\ModuleUninstalled;
use App\Foundation\Modules\Events\ModuleUpgraded;
use App\Foundation\Modules\Events\ModuleUpgradedForTenant;
use App\Foundation\Modules\Exceptions\DependencyException;
use App\Foundation\Modules\Exceptions\InvalidManifestException;
use App\Foundation\Modules\Exceptions\ModuleNotFoundException;
use App\Foundation\Modules\Exceptions\ModuleNotInstalledException;
use App\Foundation\Modules\Exceptions\ModuleStateException;
use App\Foundation\Modules\Jobs\UpgradeModuleForTenant;
use App\Foundation\Modules\Models\InstalledModule;
use App\Foundation\Modules\Models\TenantModule;
use App\Models\Tenant;
use Composer\Semver\Semver;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use LogicException;
use Nwidart\Modules\Contracts\ActivatorInterface;

/**
 * Sole module lifecycle mutation entry point (CLAUDE.md §5). Every flow here is a
 * central-context operation — tenant work happens inside explicit initialize/end blocks.
 *
 * Upgrade rule (risk #2): migrations within one release must be expand/contract
 * (backward-compatible with the previous release's code) so tenants mid-batch never break.
 */
final class ModuleManager
{
    public function __construct(
        private readonly Application $app,
        private readonly ModuleRegistry $registry,
        private readonly DependencyResolver $resolver,
        private readonly ManifestValidator $validator,
        private readonly TenantModuleMigrator $tenantMigrator,
        private readonly PermissionSynchronizer $permissionSynchronizer,
        private readonly ActivatorInterface $activator,
        private readonly Migrator $migrator,
    ) {}

    /**
     * Install a module platform-wide (auto-installs uninstalled dependencies first).
     * Central `modules` table is the source of truth; the nwidart statuses file is
     * mirrored as a boot artifact.
     */
    public function install(string $alias): void
    {
        $this->assertCentralContext(__FUNCTION__);

        $universe = $this->registry->discovered();

        if (! array_key_exists($alias, $universe)) {
            throw ModuleNotFoundException::forAlias($alias);
        }

        foreach ($this->resolver->resolveInstallOrder([$alias], $universe) as $installAlias) {
            if ($this->registry->isInstalled($installAlias)) {
                continue;
            }

            $manifest = $this->validateOnDisk($installAlias);
            $this->assertPlatformCompatible($manifest);

            $this->runCentralModuleMigrations($manifest);

            InstalledModule::query()->updateOrCreate(['alias' => $manifest->alias], [
                'name' => $manifest->name,
                'version' => $manifest->version,
                'core' => $manifest->core,
                'installed_at' => now(),
            ]);

            $this->activator->setActiveByName($manifest->name, true);
            $this->registry->flush();

            $this->lifecycle($manifest)?->installed();

            ModuleInstalled::dispatch($manifest->alias, $manifest->version);
        }
    }

    /**
     * Enable a module for one tenant — migrations + idempotent seed inside tenant
     * context, enablement row written only after success. Auto-enables missing
     * dependencies in topological order. This exact flow serves the CLI, the signup
     * provisioning job and tests alike (§5's invariant).
     */
    public function enableForTenant(string $alias, Tenant $tenant): void
    {
        $this->assertCentralContext(__FUNCTION__);

        if (! $this->registry->isInstalled($alias)) {
            throw ModuleNotInstalledException::forAlias($alias);
        }

        $order = $this->resolver->resolveInstallOrder([$alias], $this->registry->installed());

        foreach ($order as $enableAlias) {
            if (! $this->registry->isEnabledFor($enableAlias, $tenant)) {
                $this->enableOne($this->registry->manifest($enableAlias), $tenant);
            }
        }
    }

    public function disableForTenant(string $alias, Tenant $tenant, bool $cascade = false): void
    {
        $this->assertCentralContext(__FUNCTION__);

        $manifest = $this->registry->manifest($alias);

        if ($manifest->core) {
            throw ModuleStateException::cannotDisableCore($alias);
        }

        if (! $this->registry->isEnabledFor($alias, $tenant)) {
            throw ModuleStateException::notEnabledForTenant($alias, (string) $tenant->getTenantKey());
        }

        $enabledDependents = array_values(array_intersect(
            $this->resolver->dependentsOf($alias, $this->registry->installed()),
            $this->registry->enabledFor($tenant),
        ));

        if ($enabledDependents !== []) {
            if (! $cascade) {
                throw DependencyException::hasEnabledDependents($alias, $enabledDependents);
            }

            foreach ($enabledDependents as $dependent) {
                $this->disableForTenant($dependent, $tenant, cascade: true);
            }
        }

        $this->inTenantContext($tenant, function () use ($manifest, $tenant): void {
            $this->lifecycle($manifest)?->disabled($tenant);
        });

        TenantModule::query()
            ->where('tenant_id', (string) $tenant->getTenantKey())
            ->where('module', $alias)
            ->update(['enabled' => false]); // migrated_version kept — data stays intact

        $this->registry->flushTenantCache($tenant);

        ModuleDisabledForTenant::dispatch($alias, (string) $tenant->getTenantKey());
    }

    /**
     * Purge is explicit and separate from disable (§5): rolls back the module's tenant
     * migrations (dropping its tables) and removes the enablement row entirely.
     */
    public function purgeForTenant(string $alias, Tenant $tenant): void
    {
        $this->assertCentralContext(__FUNCTION__);

        $manifest = $this->registry->manifest($alias);
        $tenantId = (string) $tenant->getTenantKey();

        $row = TenantModule::query()
            ->where('tenant_id', $tenantId)
            ->where('module', $alias)
            ->first();

        if ($row === null || $row->enabled) {
            throw ModuleStateException::purgeRequiresDisabled($alias, $tenantId);
        }

        $this->inTenantContext($tenant, function () use ($manifest, $tenant): void {
            $this->lifecycle($manifest)?->purging($tenant);
            $this->tenantMigrator->reset($manifest);
        });

        $row->delete();
        $this->registry->flushTenantCache($tenant);

        ModulePurgedForTenant::dispatch($alias, $tenantId);
    }

    /**
     * Platform-wide upgrade: central migrations + version bump, then a queued batch
     * fans the per-tenant upgrade out over every tenant with the module enabled.
     * Returns null when no tenant has the module enabled.
     */
    public function upgrade(string $alias): ?Batch
    {
        $this->assertCentralContext(__FUNCTION__);

        if (! $this->registry->isInstalled($alias)) {
            throw ModuleNotInstalledException::forAlias($alias);
        }

        $manifest = $this->validateOnDisk($alias);
        $this->assertPlatformCompatible($manifest);

        $this->runCentralModuleMigrations($manifest);

        /** @var InstalledModule $row */
        $row = InstalledModule::query()->where('alias', $alias)->firstOrFail();
        $fromVersion = $row->version;

        $row->update([
            'name' => $manifest->name,
            'version' => $manifest->version,
            'core' => $manifest->core,
        ]);

        $this->registry->flush();

        ModuleUpgraded::dispatch($alias, $fromVersion, $manifest->version);

        $tenantIds = TenantModule::query()
            ->where('module', $alias)
            ->where('enabled', true)
            ->pluck('tenant_id');

        if ($tenantIds->isEmpty()) {
            return null;
        }

        return Bus::batch(
            $tenantIds->map(fn (string $tenantId) => new UpgradeModuleForTenant($alias, $tenantId))->all(),
        )->allowFailures()->name("zenon:module:upgrade:{$alias}")->dispatch();
    }

    /**
     * The shared per-tenant upgrade path (batch job + doctor re-runs): pending tenant
     * migrations + re-seed, idempotent via the tenant's migrations ledger.
     */
    public function upgradeForTenant(string $alias, Tenant $tenant): void
    {
        $this->assertCentralContext(__FUNCTION__);

        $manifest = $this->registry->manifest($alias);
        $tenantId = (string) $tenant->getTenantKey();

        if (! $this->registry->isEnabledFor($alias, $tenant)) {
            throw ModuleStateException::notEnabledForTenant($alias, $tenantId);
        }

        $this->inTenantContext($tenant, function () use ($manifest, $tenant): void {
            $this->tenantMigrator->migrate($manifest);
            $this->permissionSynchronizer->sync($manifest);
            $this->runSeeder($manifest);
            $this->lifecycle($manifest)?->enabled($tenant);
        });

        TenantModule::query()
            ->where('tenant_id', $tenantId)
            ->where('module', $alias)
            ->update(['migrated_version' => $manifest->version]);

        $this->registry->flushTenantCache($tenant);

        ModuleUpgradedForTenant::dispatch($alias, $tenantId, $manifest->version);
    }

    /** Uninstall platform-wide — requires zero enabled tenants; tenant data untouched. */
    public function uninstall(string $alias): void
    {
        $this->assertCentralContext(__FUNCTION__);

        $manifest = $this->registry->manifest($alias);

        $enabledTenants = array_values(TenantModule::query()
            ->where('module', $alias)
            ->where('enabled', true)
            ->pluck('tenant_id')
            ->map(fn ($id) => (string) $id)
            ->all());

        if ($enabledTenants !== []) {
            throw ModuleStateException::uninstallWithEnabledTenants($alias, $enabledTenants);
        }

        InstalledModule::query()->where('alias', $alias)->delete(); // FK cascades disabled tenant_modules rows

        $this->activator->setActiveByName($manifest->name, false);
        $this->registry->flush();

        ModuleUninstalled::dispatch($alias);
    }

    private function enableOne(ManifestData $manifest, Tenant $tenant): void
    {
        $tenantId = (string) $tenant->getTenantKey();

        $this->lifecycle($manifest)?->enabling($tenant); // central context, pre-migration

        $this->inTenantContext($tenant, function () use ($manifest, $tenant): void {
            $this->tenantMigrator->migrate($manifest);
            $this->permissionSynchronizer->sync($manifest);
            $this->runSeeder($manifest);
            $this->lifecycle($manifest)?->enabled($tenant);
        });

        // Written only after tenant work succeeded — a failure leaves no row and the
        // tenant migrations ledger makes the retry idempotent.
        TenantModule::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'module' => $manifest->alias],
            ['enabled' => true, 'migrated_version' => $manifest->version],
        );

        $this->registry->flushTenantCache($tenant);

        ModuleEnabledForTenant::dispatch($manifest->alias, $tenantId);
    }

    /** Re-validates the on-disk manifest (install/upgrade must never trust stale data). */
    private function validateOnDisk(string $alias): ManifestData
    {
        $discovered = $this->registry->discovered()[$alias] ?? throw ModuleNotFoundException::forAlias($alias);

        $raw = json_decode((string) file_get_contents($discovered->path.DIRECTORY_SEPARATOR.'module.json'), true);

        if (! is_array($raw)) {
            throw new InvalidManifestException($discovered->path, ['module.json' => ['module.json is not valid JSON.']]);
        }

        return $this->validator->validate($raw, $discovered->path);
    }

    private function assertPlatformCompatible(ManifestData $manifest): void
    {
        /** @var string $platformVersion */
        $platformVersion = config('zenon.platform_version');

        if (! Semver::satisfies($platformVersion, $manifest->platform)) {
            throw ModuleStateException::platformIncompatible($manifest->alias, $manifest->platform, $platformVersion);
        }
    }

    private function runCentralModuleMigrations(ManifestData $manifest): void
    {
        $path = $manifest->path.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'central';

        if (! is_dir($path)) {
            return;
        }

        if (! $this->migrator->repositoryExists()) {
            $this->migrator->getRepository()->createRepository();
        }

        $this->migrator->run([$path]);
    }

    private function runSeeder(ManifestData $manifest): void
    {
        $class = $this->baseNamespace($manifest).'\\Database\\Seeders\\'.$manifest->name.'DatabaseSeeder';

        if (! class_exists($class)) {
            return;
        }

        $seeder = $this->app->make($class);

        if ($seeder instanceof Seeder) {
            $seeder->setContainer($this->app);
            $seeder->__invoke();
        }
    }

    private function lifecycle(ManifestData $manifest): ?ModuleLifecycle
    {
        $class = $this->baseNamespace($manifest).'\\'.$manifest->name.'Module';

        if (! class_exists($class) || ! is_a($class, ModuleLifecycle::class, true)) {
            return null;
        }

        /** @var ModuleLifecycle */
        return $this->app->make($class);
    }

    /** Module base namespace, derived from its first registered provider FQCN. */
    private function baseNamespace(ManifestData $manifest): string
    {
        $provider = $manifest->providers[0] ?? throw new LogicException(
            sprintf('Module [%s] declares no providers.', $manifest->alias),
        );

        return Str::before($provider, '\\Providers\\');
    }

    /**
     * @param  callable(): void  $callback
     */
    private function inTenantContext(Tenant $tenant, callable $callback): void
    {
        tenancy()->initialize($tenant);

        try {
            $callback();
        } finally {
            tenancy()->end();
        }
    }

    private function assertCentralContext(string $operation): void
    {
        if (tenancy()->initialized) {
            throw new LogicException(sprintf(
                'ModuleManager::%s() is a central-context operation and must not run inside an initialized tenancy.',
                $operation,
            ));
        }
    }
}
