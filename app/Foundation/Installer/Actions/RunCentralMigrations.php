<?php

namespace App\Foundation\Installer\Actions;

use Illuminate\Support\Facades\Artisan;

/**
 * The installer's "migrate" step (CLAUDE.md §7 Phase 8 Task 6): runs the platform's
 * central migrations (tenants, domains, modules, tenant_modules, jobs/cache/sessions —
 * CLAUDE.md §1) against whatever `database.default` connection the just-written .env
 * resolves to on this request's fresh boot. `migrate --force` is naturally idempotent —
 * a re-POST of this step (a wizard resumed after a crash) is a safe no-op.
 */
final class RunCentralMigrations
{
    public function handle(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }
}
