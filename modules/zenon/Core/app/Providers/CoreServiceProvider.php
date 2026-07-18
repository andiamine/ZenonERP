<?php

namespace Modules\Core\Providers;

use App\Foundation\Company\Contracts\CompanyResolver;
use App\Foundation\Modules\ModuleServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\Companies\CompanyDirectory;
use Modules\Core\Contracts\Settings\SettingDefinition;
use Modules\Core\Contracts\Settings\SettingsReader;
use Modules\Core\Contracts\Settings\SettingsRegistrar;
use Modules\Core\Contracts\Settings\SettingsRepository as SettingsRepositoryContract;
use Modules\Core\Services\CompanyDirectoryService;
use Modules\Core\Services\SettingsRegistry;
use Modules\Core\Services\SettingsRepository;

class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Core';

    protected string $nameLower = 'core';

    public function register(): void
    {
        parent::register();

        // One SettingsRegistry per request/worker cycle — every module provider's
        // boot() registers its definitions into the SAME instance (bound below).
        $this->app->scoped(SettingsRegistry::class);

        $this->app->bind(SettingsRegistrar::class, fn ($app) => $app->make(SettingsRegistry::class));

        $this->app->bind(SettingsReader::class, SettingsRepository::class);
        $this->app->bind(SettingsRepositoryContract::class, SettingsRepository::class);

        $this->app->bind(CompanyDirectory::class, CompanyDirectoryService::class);
        // The Foundation port SetCurrentCompany depends on (CLAUDE.md §8) — bound here
        // because Foundation must never `use Modules\...` directly (CLAUDE.md §5).
        $this->app->bind(CompanyResolver::class, CompanyDirectoryService::class);
    }

    public function boot(): void
    {
        parent::boot();

        $registrar = $this->app->make(SettingsRegistrar::class);

        $registrar->register(
            new SettingDefinition('core.default_currency', 'string', 'USD'),
            new SettingDefinition('core.date_format', 'string', 'Y-m-d'),
            new SettingDefinition('core.timezone', 'string', 'UTC'),
            new SettingDefinition('core.fiscal_year_start_month', 'int', 1),
        );

        // Admin super-user (spatie-recommended pattern): `null` falls through to normal
        // policy/permission checks, it never returns false. Guarded `instanceof` keeps
        // CentralUser (a different guard, no roles table) out of this check.
        Gate::before(fn ($user) => $user instanceof User && $user->hasRole('admin') ? true : null);
    }
}
