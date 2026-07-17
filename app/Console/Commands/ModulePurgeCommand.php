<?php

namespace App\Console\Commands;

use App\Foundation\Modules\ModuleManager;
use App\Models\Tenant;
use Illuminate\Console\Command;
use RuntimeException;

class ModulePurgeCommand extends Command
{
    protected $signature = 'zenon:module:purge {alias} {--tenant= : Tenant id (required)} {--force : Skip the confirmation prompt}';

    protected $description = 'Purge a disabled module for a tenant — rolls back its tenant migrations (DROPS its tables)';

    public function handle(ModuleManager $manager): int
    {
        $alias = (string) $this->argument('alias');
        $tenantId = $this->option('tenant');

        if (! is_string($tenantId)) {
            $this->components->error('The --tenant=<id> option is required.');

            return self::FAILURE;
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            $this->components->error(sprintf('Tenant [%s] not found.', $tenantId));

            return self::FAILURE;
        }

        $confirmed = (bool) $this->option('force') || $this->confirm(sprintf(
            'This DROPS all [%s] tables in tenant [%s]\'s database. Continue?', $alias, $tenantId,
        ));

        if (! $confirmed) {
            $this->components->info('Aborted.');

            return self::FAILURE;
        }

        try {
            $manager->purgeForTenant($alias, $tenant);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Purged [%s] for tenant [%s].', $alias, $tenantId));

        return self::SUCCESS;
    }
}
