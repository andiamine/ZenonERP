<?php

namespace App\Foundation\Tenancy\Actions;

use App\Foundation\Tenancy\Jobs\ProvisionTenantModules;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Provisions the single standalone tenant (CLAUDE.md §7, Phase 8 Task 4) — the
 * installer wizard's counterpart to {@see CreateTenant}, which refuses outright in
 * standalone mode. The tenant id is always the fixed 'default' (predictable for docs
 * and `tenants:migrate --tenants=default`); the database itself is pre-created by the
 * user (Plesk/cPanel grants no CREATE DATABASE privilege) and supplied via $dbName, so
 * `tenancy_create_database` is always false — the exact pattern proven in
 * tests/Feature/Tenancy/PreCreatedDatabaseTest.php.
 *
 * Resumable by construction: if a previous run created the tenant row but died before
 * finishing (e.g. the process was killed mid-pipeline), calling handle() again does
 * NOT try to recreate the tenant (which would collide on the id) — it re-runs the
 * completion steps directly (tenant migrate + module provisioning + domain row), each
 * idempotent on its own (the tenant's own migrations ledger; ModuleManager::
 * enableForTenant skips already-enabled modules; the domain existence check below). A
 * second call after full success walks the exact same idempotent path, so it is also a
 * safe no-op — "resume" and "already done" are not different cases here.
 */
final class CreateStandaloneTenant
{
    /**
     * @param  string  $name  Tenant display name.
     * @param  string  $dbName  Name of the pre-created tenant database (installer-supplied).
     * @param  string|null  $dbConnection  Template connection the tenant DB config is read
     *                                     from (production: 'standalone', the TENANT_DB_*
     *                                     env template — see config/database.php). Null omits
     *                                     `tenancy_db_connection` entirely so DatabaseConfig
     *                                     falls back to `tenancy.database.template_tenant_connection`
     *                                     — tests pass null to land on the central sqlite
     *                                     template, mirroring PreCreatedDatabaseTest.
     */
    public function handle(string $name, string $dbName, ?string $dbConnection = 'standalone'): Tenant
    {
        $tenant = Tenant::find('default');

        if ($tenant === null) {
            $attributes = [
                'id' => 'default',
                'name' => $name,
                'tenancy_db_name' => $dbName,
                'tenancy_create_database' => false,
            ];

            if ($dbConnection !== null) {
                $attributes['tenancy_db_connection'] = $dbConnection;
            }

            // Synchronous TenantCreated pipeline runs here: CreateTenantDatabase (swallows
            // the pipeline-breaking `false` stancl's CreateDatabase returns for
            // tenancy_create_database=false) → MigrateDatabase → ProvisionTenantModules
            // (Task 2's pre-created-DB seam).
            $tenant = Tenant::create($attributes);
        } else {
            // Resume: the tenant row already exists, so re-run the idempotent completion
            // steps directly instead of going through Tenant::create() again.
            Artisan::call('tenants:migrate', ['--tenants' => [$tenant->getTenantKey()]]);
            app()->call([new ProvisionTenantModules($tenant), 'handle']);
        }

        $this->ensureDomain($tenant);

        return $tenant;
    }

    private function ensureDomain(Tenant $tenant): void
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new RuntimeException("Cannot provision the standalone tenant domain: config('app.url') has no parsable host.");
        }

        if (! $tenant->domains()->where('domain', $host)->exists()) {
            $tenant->domains()->create(['domain' => $host]);
        }
    }
}
