<?php

namespace App\Foundation\Modules\Jobs;

use App\Foundation\Modules\ModuleManager;
use App\Models\Tenant;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * One tenant's slice of a module upgrade batch. Failures are caught and reported —
 * never rethrown — so one broken tenant cannot halt the fan-out on any queue driver
 * (risk #2). The failed tenant keeps its old migrated_version; doctor reports the
 * drift and a re-run converges via the tenant migrations ledger.
 */
class UpgradeModuleForTenant implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public string $alias,
        public string $tenantId,
    ) {}

    public function handle(ModuleManager $manager): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return; // tenant deleted mid-batch — nothing to do
        }

        try {
            $manager->upgradeForTenant($this->alias, $tenant);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
