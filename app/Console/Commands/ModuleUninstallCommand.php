<?php

namespace App\Console\Commands;

use App\Foundation\Modules\ModuleManager;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleUninstallCommand extends Command
{
    protected $signature = 'zenon:module:uninstall {alias}';

    protected $description = 'Uninstall a module platform-wide (requires zero enabled tenants; tenant data is untouched)';

    public function handle(ModuleManager $manager): int
    {
        $alias = (string) $this->argument('alias');

        try {
            $manager->uninstall($alias);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Module [%s] uninstalled.', $alias));

        return self::SUCCESS;
    }
}
