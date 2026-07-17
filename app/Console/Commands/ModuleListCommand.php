<?php

namespace App\Console\Commands;

use App\Foundation\Modules\ManifestData;
use App\Foundation\Modules\Models\TenantModule;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Console\Command;

class ModuleListCommand extends Command
{
    protected $signature = 'zenon:module:list';

    protected $description = 'List discovered modules with install state and per-tenant enablement counts';

    public function handle(ModuleRegistry $registry): int
    {
        $discovered = $registry->discovered();

        if ($discovered === []) {
            $this->components->info('No modules discovered.');

            return self::SUCCESS;
        }

        $enabledCounts = TenantModule::query()
            ->where('enabled', true)
            ->selectRaw('module, count(*) as aggregate')
            ->groupBy('module')
            ->pluck('aggregate', 'module');

        $this->table(
            ['Alias', 'Name', 'Id', 'Version', 'Core', 'Installed', 'Enabled tenants'],
            collect($discovered)->map(fn (ManifestData $m) => [
                $m->alias,
                $m->name,
                $m->id,
                $m->version,
                $m->core ? 'yes' : 'no',
                $registry->isInstalled($m->alias) ? 'yes' : 'no',
                (string) ($enabledCounts[$m->alias] ?? 0),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
