<?php

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
