<?php

namespace App\Foundation\Tenancy\Jobs;

use Stancl\Tenancy\Jobs\DeleteDatabase;

/**
 * Wraps stancl's DeleteDatabase with the pre-created-DB guard it doesn't have.
 *
 * Unlike CreateDatabase, stancl's DeleteDatabase::handle() has NO check for the
 * tenant's `tenancy_create_database` internal — it unconditionally drops/unlinks
 * the tenant's database (vendor/stancl/tenancy/src/Jobs/DeleteDatabase.php). For a
 * standalone tenant whose database was pre-created by the installer (against
 * user-supplied credentials tenancy does not own), that would destroy the user's
 * data. Early-return here instead of delegating in that case.
 */
class DeleteTenantDatabase extends DeleteDatabase
{
    public function handle(): void
    {
        if ($this->tenant->getInternal('create_database') === false) {
            // Tenancy does not own this database — leave it on disk / in the DB server.
            return;
        }

        parent::handle();
    }
}
