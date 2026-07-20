<?php

namespace App\Foundation\Tenancy\Jobs;

use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;

/**
 * Wraps stancl's CreateDatabase to swallow its pipeline-breaking `false` return.
 *
 * Stancl's own job intentionally returns `false` when the tenant's
 * `tenancy_create_database` internal is `false` (standalone mode: the database was
 * already created by the installer against user-supplied credentials — no
 * CREATE DATABASE privilege needed) — see vendor/stancl/tenancy/src/Jobs/CreateDatabase.php:35,
 * whose own comment says "Terminate execution of this job & other jobs in the
 * pipeline". `JobPipeline` acts on that literally: ANY `false` return from ANY job
 * aborts every remaining job (verified vendor/stancl/jobpipeline/src/JobPipeline.php:79),
 * which would also skip `MigrateDatabase` + `ProvisionTenantModules`. Returning void
 * here instead is what lets those keep running for pre-created-DB tenants.
 */
class CreateTenantDatabase extends CreateDatabase
{
    public function handle(DatabaseManager $databaseManager): void
    {
        parent::handle($databaseManager);
    }
}
