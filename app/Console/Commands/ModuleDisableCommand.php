<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenants;
use App\Foundation\Modules\ModuleManager;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleDisableCommand extends Command
{
    use ResolvesTenants;

    protected $signature = 'zenon:module:disable {alias} {--tenant= : Tenant id} {--all-tenants : Disable for every tenant} {--cascade : Also disable enabled dependents}';

    protected $description = 'Disable a module for tenant(s) — routes/hooks gate off immediately, data stays intact';

    public function handle(ModuleManager $manager): int
    {
        $alias = (string) $this->argument('alias');
        $tenants = $this->resolveTenants();

        if ($tenants === null) {
            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            try {
                $manager->disableForTenant($alias, $tenant, cascade: (bool) $this->option('cascade'));
                $this->components->info(sprintf('Disabled [%s] for tenant [%s].', $alias, $tenant->getTenantKey()));
            } catch (RuntimeException $e) {
                $this->components->error(sprintf('Tenant [%s]: %s', $tenant->getTenantKey(), $e->getMessage()));

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
