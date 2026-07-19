<?php

namespace App\Providers;

use App\Foundation\Company\CurrentCompany;
use App\Foundation\Frontend\GeneratedModuleRegistry;
use App\Foundation\Hooks\HookBus;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped (not singleton): the registry memoizes per-tenant enablement — memos
        // must reset between requests/jobs (Octane, queue workers).
        $this->app->scoped(ModuleRegistry::class);

        // Singleton (NOT scoped): filter registrations happen in provider boot(), once
        // per process — a scoped bus would be flushed each request/job lifecycle and
        // lose them. Per-tenant variability lives entirely in the gating check at
        // filter() time, which resolves the scoped ModuleRegistry lazily per call.
        $this->app->singleton(HookBus::class);

        // Scoped for the same reason: memoizes a filesystem read per request/job.
        $this->app->scoped(GeneratedModuleRegistry::class);

        // Scoped: the active company id is per-request state (SetCurrentCompany writes
        // it, CompanyScope reads it) and must never survive past the request/job boundary.
        $this->app->scoped(CurrentCompany::class);

        // Laravel registers the migrator only under the string key 'migrator' — alias it
        // so Foundation services can type-hint Migrator.
        $this->app->bind(Migrator::class, fn ($app) => $app->make('migrator'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
