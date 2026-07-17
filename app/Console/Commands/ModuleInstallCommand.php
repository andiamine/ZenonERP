<?php

namespace App\Console\Commands;

use App\Foundation\Modules\ModuleManager;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleInstallCommand extends Command
{
    protected $signature = 'zenon:module:install {alias : Module alias to install (dependencies install automatically)}';

    protected $description = 'Install a module platform-wide (validates manifest, resolves dependencies, runs central migrations)';

    public function handle(ModuleManager $manager, ModuleRegistry $registry): int
    {
        $alias = (string) $this->argument('alias');

        $before = array_keys($registry->installed());

        try {
            $manager->install($alias);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $installed = array_values(array_diff(array_keys($registry->installed()), $before));

        if ($installed === []) {
            $this->components->info(sprintf('Module [%s] is already installed.', $alias));
        } else {
            $this->components->info(sprintf('Installed: %s.', implode(', ', $installed)));
        }

        return self::SUCCESS;
    }
}
