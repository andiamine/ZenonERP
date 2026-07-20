<?php

namespace App\Console\Commands;

use App\Foundation\Modules\AddonZipInstaller;
use Illuminate\Console\Command;
use RuntimeException;

class ModuleInstallZipCommand extends Command
{
    protected $signature = 'zenon:module:install-zip {path : Path to the addon zip}';

    protected $description = 'Extract a third-party addon zip into modules/thirdparty and run the normal module install';

    public function handle(AddonZipInstaller $installer): int
    {
        $path = $this->resolvePath((string) $this->argument('path'));

        try {
            $alias = $installer->install($path);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Installed [%s] from [%s]. Enable per tenant with zenon:module:enable.',
            $alias, $path,
        ));

        return self::SUCCESS;
    }

    /** Accepts an absolute path, a path relative to the current working directory, or one relative to base_path(). */
    private function resolvePath(string $path): string
    {
        if (is_file($path)) {
            return $path;
        }

        $baseRelative = base_path($path);

        return is_file($baseRelative) ? $baseRelative : $path;
    }
}
