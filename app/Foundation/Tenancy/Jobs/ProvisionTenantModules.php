<?php

namespace App\Foundation\Tenancy\Jobs;

use App\Foundation\Modules\ManifestData;
use App\Foundation\Modules\ModuleManager;
use App\Foundation\Modules\ModuleRegistry;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs at the end of the TenantCreated pipeline (after CreateDatabase + the platform's
 * base MigrateDatabase): enables every core module plus zenon.default_modules via THE
 * identical ModuleManager::enableForTenant flow — new-tenant provisioning and enabling
 * a module for an existing tenant share one code path (CLAUDE.md §5's invariant).
 */
class ProvisionTenantModules implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(protected Tenant $tenant) {}

    public function handle(ModuleManager $manager, ModuleRegistry $registry): void
    {
        /** @var list<string> $defaults */
        $defaults = config('zenon.default_modules', []);

        $installed = $registry->installed();
        $installedAliases = array_keys($installed);

        // A default alias that isn't installed yet is skipped, not fatal — mirrors the
        // "installed ∩ core" filter below and keeps provisioning tolerant of a config
        // declaring a module before its code is deployed. Unlike core-ness (read FROM
        // $installed, so it has no failure mode), default_modules is independent
        // human-edited config that can drift from deployed reality — that drift must
        // stay visible, hence the warning below.
        $skipped = array_values(array_diff($defaults, $installedAliases));

        if ($skipped !== []) {
            Log::warning('tenant.provisioning.default_module_skipped', [
                'tenant' => (string) $this->tenant->getTenantKey(),
                'skipped' => $skipped,
            ]);
        }

        $aliases = collect($installed)
            ->filter(fn (ManifestData $manifest) => $manifest->core)
            ->keys()
            ->merge(array_values(array_intersect($defaults, $installedAliases)))
            ->unique()
            ->values();

        foreach ($aliases as $alias) {
            $manager->enableForTenant($alias, $this->tenant);
        }
    }
}
