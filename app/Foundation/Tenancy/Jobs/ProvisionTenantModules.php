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

        $aliases = collect($registry->installed())
            ->filter(fn (ManifestData $manifest) => $manifest->core)
            ->keys()
            ->merge($defaults)
            ->unique()
            ->values();

        foreach ($aliases as $alias) {
            $manager->enableForTenant($alias, $this->tenant);
        }
    }
}
