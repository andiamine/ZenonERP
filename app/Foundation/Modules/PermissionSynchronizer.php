<?php

namespace App\Foundation\Modules;

use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs a module manifest's declared permissions into the CURRENT tenant's spatie
 * permission tables (CLAUDE.md §9.1). Called by ModuleManager inside the tenant-context
 * closure on both enable and upgrade, right after TenantModuleMigrator — same guard
 * pattern (throw if tenancy isn't initialized) since it writes to the tenant connection.
 *
 * Policy: CREATE-ONLY, NEVER delete. Deleting a permission on upgrade would FK-cascade
 * role_has_permissions/model_has_permissions rows and silently revoke grants a tenant
 * admin made deliberately. A permission renamed or removed from a manifest leaves a
 * harmless orphan row in the tenant DB — `zenon:module:doctor` reporting orphans is a
 * later, explicit concern, not this class's job.
 */
final class PermissionSynchronizer
{
    /**
     * @throws LogicException when called outside an initialized tenant context
     */
    public function sync(ManifestData $manifest): void
    {
        if (! tenancy()->initialized) {
            throw new LogicException('PermissionSynchronizer::sync() must run inside an initialized tenant context.');
        }

        if ($manifest->permissions === []) {
            return;
        }

        foreach ($manifest->permissions as $name) {
            // Query-level firstOrCreate bypasses spatie's own cache-aware helpers and is
            // naturally idempotent — safe to call on every enable and every upgrade.
            Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
