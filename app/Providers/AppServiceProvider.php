<?php

namespace App\Providers;

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
