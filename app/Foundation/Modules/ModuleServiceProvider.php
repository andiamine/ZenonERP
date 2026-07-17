<?php

namespace App\Foundation\Modules;

use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Support\ModuleServiceProvider as NwidartModuleServiceProvider;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/**
 * Base provider every ZenonERP module provider extends (CLAUDE.md §2). Composes with
 * nwidart's Support\ModuleServiceProvider (config/lang/commands registration) but owns
 * route mapping itself: the module's routes/api.php is auto-wrapped as
 * /api/v1/{alias}/* behind tenancy + the module.enabled gate.
 *
 * Routes register for every INSTALLED module on every boot (global route:cache stays
 * valid); per-tenant enablement is gated at runtime only (§5 — no per-tenant routes).
 *
 * Deliberately NOT calling loadMigrationsFrom(): module migrations live in
 * database/migrations/{tenant,central} and run only through the module lifecycle.
 */
abstract class ModuleServiceProvider extends NwidartModuleServiceProvider
{
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
        ])
            ->prefix('api/v1/'.$this->alias())
            ->name($this->alias().'.')
            ->group($routes);
    }
}
