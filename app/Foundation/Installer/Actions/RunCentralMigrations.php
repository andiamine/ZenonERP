<?php

namespace App\Foundation\Installer\Actions;

use App\Foundation\Modules\ModuleManager;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Artisan;

/**
 * The installer's "migrate" step (CLAUDE.md §7 Phase 8 Task 6): runs the platform's
 * central migrations (tenants, domains, modules, tenant_modules, jobs/cache/sessions —
 * CLAUDE.md §1) against whatever `database.default` connection the just-written .env
 * resolves to on this request's fresh boot, then installs every discovered first-party
 * module platform-wide (central `modules` rows, via {@see ModuleRegistry::discoveredFirstParty()})
 * so the Tenant step has something to enable.
 *
 * A fresh standalone extract has NO code path that installs modules before this step —
 * unlike a deploy-pipeline release, the wizard is the only actor that ever runs on this
 * host, and it never shells out (`zenon:module:install` orchestrates nothing extra
 * itself, but is still a CLI command the standalone acceptance criterion rules out).
 * `ModuleManager::install()` is the right seam: confirmed to do ONLY central-migration +
 * `modules` row + statuses-file + lifecycle/event work — no frontend generation, no
 * `npm run build` (that pairing lives one layer up, in the deploy-pipeline CLI flow
 * documented in CLAUDE.md §5, never invoked here). Third-party addons under
 * `modules/thirdparty/` are deliberately never auto-installed by the wizard.
 *
 * Both `migrate --force` and `ModuleManager::install()` are independently idempotent
 * (install() skips already-installed aliases and resolves each alias's own dependency
 * closure from the full discovered universe, so calling it once per alias in any order
 * is dependency-safe) — a re-POST of this step, whether a clean resume or a retry after
 * a mid-request crash, is a safe no-op / converges to done.
 */
final class RunCentralMigrations
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleManager $manager,
    ) {}

    public function handle(): void
    {
        Artisan::call('migrate', ['--force' => true]);

        $aliases = collect($this->registry->discoveredFirstParty())
            ->keys()
            ->sort() // deterministic; install() resolves each alias's own dependency order regardless
            ->values();

        foreach ($aliases as $alias) {
            $this->manager->install($alias);
        }
    }
}
