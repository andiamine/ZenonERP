<?php

namespace App\Foundation\Modules;

use App\Foundation\Company\SetCurrentCompany;
use App\Foundation\Hooks\Extend;
use App\Foundation\Hooks\HookBus;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Support\ModuleServiceProvider as NwidartModuleServiceProvider;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/**
 * Base provider every ZenonERP module provider extends (CLAUDE.md §2). Composes with
 * nwidart's Support\ModuleServiceProvider (config/lang/commands registration) but owns
 * route mapping itself: the module's routes/api.php is auto-wrapped as
 * /api/v1/{alias}/* behind tenancy + the module.enabled gate + company resolution (§8).
 *
 * Routes register for every INSTALLED module on every boot (global route:cache stays
 * valid); per-tenant enablement is gated at runtime only (§5 — no per-tenant routes).
 *
 * Deliberately NOT calling loadMigrationsFrom(): module migrations live in
 * database/migrations/{tenant,central} and run only through the module lifecycle.
 */
abstract class ModuleServiceProvider extends NwidartModuleServiceProvider
{
    private ?Extend $extend = null;

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->mapApiRoutes();
    }

    public function register(): void
    {
        //
    }

    /** Module alias == manifest "alias". Convention: the lowercased module name; override if they differ. */
    protected function alias(): string
    {
        return $this->nameLower;
    }

    /**
     * The module's hook/listener registration facade (§6) — call from boot().
     * Pre-stamped with this module's alias so every filter/listener registered through
     * it is tenant-gated automatically; the only sanctioned registration path (§13 risk #1).
     */
    protected function extend(): Extend
    {
        return $this->extend ??= new Extend(
            $this->app->make(HookBus::class),
            $this->app->make(Dispatcher::class),
            $this->alias(),
        );
    }

    protected function mapApiRoutes(): void
    {
        $routes = module_path($this->name, 'routes/api.php');

        if (! file_exists($routes)) {
            return;
        }

        Route::middleware([
            'api',
            InitializeTenancyBySubdomain::class, // TenancyServiceProvider priority-sorts tenancy middleware first
            PreventAccessFromCentralDomains::class,
            'module.enabled:'.$this->alias(),
            SetCurrentCompany::class, // every module route gets company context automatically (§13 risk #1)
        ])
            ->prefix('api/v1/'.$this->alias())
            ->name($this->alias().'.')
            ->group($routes);
    }
}
