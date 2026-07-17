<?php

namespace App\Console\Commands;

use App\Foundation\Modules\ModuleManager;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleUpgradeCommand extends Command
{
    protected $signature = 'zenon:module:upgrade {alias}';

    protected $description = 'Upgrade a module: central migrations + version bump, then fan the per-tenant upgrade out as a job batch';

    public function handle(ModuleManager $manager): int
    {
        $alias = (string) $this->argument('alias');

        try {
            $batch = $manager->upgrade($alias);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($batch === null) {
            $this->components->info(sprintf('Module [%s] upgraded centrally; no tenants have it enabled.', $alias));
        } else {
            $this->components->info(sprintf(
                'Module [%s] upgraded centrally; batch [%s] dispatched for %d tenant(s).',
                $alias, $batch->id, $batch->totalJobs,
            ));
        }

        return self::SUCCESS;
    }
}
