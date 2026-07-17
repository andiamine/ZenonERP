<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesTenants;
use App\Foundation\Modules\ModuleManager;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleEnableCommand extends Command
{
    use ResolvesTenants;

    protected $signature = 'zenon:module:enable {alias} {--tenant= : Tenant id} {--all-tenants : Enable for every tenant}';

    protected $description = 'Enable a module for tenant(s) — migrates + seeds inside each tenant DB (dependencies auto-enable)';

    public function handle(ModuleManager $manager): int
    {
        $alias = (string) $this->argument('alias');
        $tenants = $this->resolveTenants();

        if ($tenants === null) {
            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            try {
                $manager->enableForTenant($alias, $tenant);
                $this->components->info(sprintf('Enabled [%s] for tenant [%s].', $alias, $tenant->getTenantKey()));
            } catch (RuntimeException $e) {
                $this->components->error(sprintf('Tenant [%s]: %s', $tenant->getTenantKey(), $e->getMessage()));

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
