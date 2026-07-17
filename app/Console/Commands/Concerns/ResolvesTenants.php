<?php

namespace App\Console\Commands\Concerns;

use App\Models\Tenant;
use Illuminate\Support\LazyCollection;

/**
 * Shared --tenant= / --all-tenants option handling for zenon:module:* commands.
 */
trait ResolvesTenants
{
    /**
     * @return LazyCollection<int, Tenant>|null null = invalid option combination (error already printed)
     */
    protected function resolveTenants(): ?LazyCollection
    {
        $tenantId = $this->option('tenant');
        $allTenants = (bool) $this->option('all-tenants');

        if (($tenantId !== null) === $allTenants) {
            $this->components->error('Provide exactly one of --tenant=<id> or --all-tenants.');

            return null;
        }

        if (is_string($tenantId)) {
            $tenant = Tenant::find($tenantId);

            if ($tenant === null) {
                $this->components->error(sprintf('Tenant [%s] not found.', $tenantId));

                return null;
            }

            return LazyCollection::make([$tenant]);
        }

        /** @var LazyCollection<int, Tenant> */
        return Tenant::query()->cursor();
    }
}
