<?php

use App\Foundation\Modules\ModuleManager;
use App\Foundation\Tenancy\Actions\CreateTenant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Creates tenant + domain and synchronously provisions/migrates its (file-based
 * sqlite) database — dogfoods the production CreateTenant path.
 */
function createTenant(string $subdomain, ?string $name = null): Tenant
{
    return app(CreateTenant::class)->handle($subdomain, $name);
}

function installModule(string $alias): void
{
    app(ModuleManager::class)->install($alias);
}

function enableModule(string $alias, Tenant $tenant): void
{
    app(ModuleManager::class)->enableForTenant($alias, $tenant);
}

/**
 * Standing invisibility assertion (CLAUDE.md §11): a module that is not enabled for
 * the tenant must be behaviorally invisible — its routes 404. Every future module's
 * suite reuses this.
 */
function assertModuleInvisibleFor(Tenant $tenant, string $probeUri): void
{
    test()->getJson('http://'.$tenant->getTenantKey().'.zenonerp.test'.$probeUri)->assertNotFound();
}
