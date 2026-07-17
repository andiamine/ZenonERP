<?php

namespace App\Foundation\Modules;

use Illuminate\Database\Migrations\Migrator;
use LogicException;

/**
 * Runs a module's database/migrations/tenant files inside the CURRENT tenant context.
 * The tenant's own `migrations` table is the authoritative ledger (CLAUDE.md §4) —
 * re-runs are idempotent, which is what makes upgrade retries converge (risk #2).
 */
final class TenantModuleMigrator
{
    public function __construct(private readonly Migrator $migrator) {}

    /**
     * @return list<string> migration file paths that ran
     */
    public function migrate(ManifestData $manifest): array
    {
        $path = $this->tenantMigrationsPath($manifest);

        if ($path === null) {
            return [];
        }

        return $this->onTenantConnection(function () use ($path): array {
            /** @var list<string> $ran */
            $ran = $this->migrator->run([$path]);

            return $ran;
        });
    }

    /**
     * Pending (not-yet-ran) migration names for the current tenant — doctor's drift probe.
     *
     * @return list<string>
     */
    public function pending(ManifestData $manifest): array
    {
        $path = $this->tenantMigrationsPath($manifest);

        if ($path === null) {
            return [];
        }

        return $this->onTenantConnection(function () use ($path): array {
            $files = $this->migrator->getMigrationFiles([$path]);
            $ran = $this->migrator->repositoryExists()
                ? $this->migrator->getRepository()->getRan()
                : [];

            return array_values(array_diff(array_keys($files), $ran));
        });
    }

    /**
     * Rolls back ONLY this module's tenant migrations (Migrator::reset() skips ledger
     * entries whose files are not found in the given paths) — the purge path.
     */
    public function reset(ManifestData $manifest): void
    {
        $path = $this->tenantMigrationsPath($manifest);

        if ($path === null) {
            return;
        }

        $this->onTenantConnection(function () use ($path): void {
            $this->migrator->reset([$path]);
        });
    }

    private function tenantMigrationsPath(ManifestData $manifest): ?string
    {
        $path = $manifest->path.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'tenant';

        return is_dir($path) ? $path : null;
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function onTenantConnection(callable $callback): mixed
    {
        if (! tenancy()->initialized) {
            throw new LogicException('TenantModuleMigrator must run inside an initialized tenant context.');
        }

        return $this->migrator->usingConnection('tenant', function () use ($callback) {
            if (! $this->migrator->repositoryExists()) {
                $this->migrator->getRepository()->createRepository();
            }

            return $callback();
        });
    }
}
