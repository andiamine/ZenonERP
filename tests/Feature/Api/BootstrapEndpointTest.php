<?php

use App\Foundation\Frontend\GeneratedModuleRegistry;
use Modules\Core\Models\Company;
use Modules\Core\Models\Setting;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('returns the full SPA boot payload', function () {
    $tenant = createTenant('acme');
    installModule('dummy');
    enableModule('dummy', $tenant);

    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(function () use ($user) {
        Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'sales.orders.view', 'guard_name' => 'web']);

        Role::create(['name' => 'manager', 'guard_name' => 'web'])->givePermissionTo('sales.orders.view');

        $user->givePermissionTo('reports.view'); // direct
        $user->assignRole('manager');            // via role
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/bootstrap', [], $cookie)
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'user' => ['id', 'name', 'email'],
            'tenant' => ['id', 'name'],
            'companies',
            'current_company_id',
            'enabled_modules',
            'remote_modules',
            'permissions',
            'settings',
            'locale',
            'registryHash',
        ]])
        ->assertJsonPath('data.user.email', 'user@acme.test')
        ->assertJsonPath('data.tenant.id', 'acme')
        ->assertJsonPath('data.enabled_modules', ['dummy'])
        ->assertJsonPath('data.permissions', ['reports.view', 'sales.orders.view']) // direct + via role, flattened + sorted
        ->assertJsonPath('data.companies', [])
        ->assertJsonPath('data.current_company_id', null)
        ->assertJsonPath('data.remote_modules', [])
        ->assertJsonPath('data.locale', 'en')
        // The hash advertised to the SPA is parsed from the committed registry artifact.
        ->assertJsonPath('data.registryHash', app(GeneratedModuleRegistry::class)->hash())
        ->assertJsonPath('data.registryHash', fn ($hash) => is_string($hash) && preg_match('/^[0-9a-f]{40}$/', $hash) === 1);
});

it('requires authentication', function () {
    $tenant = createTenant('acme');
    tenantUser($tenant);

    $response = statefulJson('get', 'acme.zenonerp.test', '/api/v1/bootstrap');

    assertErrorEnvelope($response, 401, 'unauthenticated');
});

it('fills companies/current_company_id/settings and gives an admin user permissions: [*] on a core-enabled tenant', function () {
    $tenant = createTenant('acme');
    installModule('core');
    enableModule('core', $tenant);

    $admin = tenantUser($tenant, ['email' => 'admin@acme.test']);
    $company = $tenant->run(function () use ($admin) {
        $company = Company::query()->where('is_default', true)->firstOrFail();
        $company->users()->attach($admin);
        $admin->assignRole('admin'); // seeded by CoreDatabaseSeeder

        return $company;
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/bootstrap', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.companies', [[
            'id' => $company->id,
            'name' => $company->name,
            'code' => $company->code,
            'currency_code' => $company->currency_code,
            'is_default' => true,
        ]])
        ->assertJsonPath('data.current_company_id', $company->id)
        ->assertJsonPath('data.settings', [
            'core.default_currency' => 'USD',
            'core.date_format' => 'Y-m-d',
            'core.timezone' => 'UTC',
            'core.fiscal_year_start_month' => 1,
        ])
        ->assertJsonPath('data.permissions', ['*']);
});

it('honors X-Company-Id: reports that company as current and returns ITS settings overrides', function () {
    $tenant = createTenant('acme');
    installModule('core');
    enableModule('core', $tenant);

    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $companyB = $tenant->run(function () use ($user) {
        // The user belongs to both the default MAIN company (A) and a second company (B).
        Company::query()->where('is_default', true)->firstOrFail()->users()->attach($user);
        $companyB = Company::factory()->create(['code' => 'BETA', 'is_default' => false]);
        $companyB->users()->attach($user);

        // A company-B-only override — bootstrap must resolve settings against B, not the default.
        Setting::query()->create([
            'company_id' => $companyB->id,
            'key' => 'core.timezone',
            'value' => 'Europe/Paris',
        ]);

        return $companyB;
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/bootstrap', [], $cookie, ['X-Company-Id' => (string) $companyB->id])
        ->assertOk()
        ->assertJsonPath('data.current_company_id', $companyB->id)
        ->assertJsonPath('data.settings', [
            'core.default_currency' => 'USD',
            'core.date_format' => 'Y-m-d',
            'core.timezone' => 'Europe/Paris', // company-B override, NOT the 'UTC' default
            'core.fiscal_year_start_month' => 1,
        ]);
});

it('rejects a foreign X-Company-Id on bootstrap with the 403 forbidden envelope', function () {
    $tenant = createTenant('acme');
    installModule('core');
    enableModule('core', $tenant);

    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $foreignId = $tenant->run(function () use ($user) {
        Company::query()->where('is_default', true)->firstOrFail()->users()->attach($user);

        // A company the user is NOT a member of — the guard that keeps the SPA's stale-company
        // clear-and-retry alive.
        return Company::factory()->create(['code' => 'OTHER', 'is_default' => false])->id;
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    assertErrorEnvelope(
        statefulJson('get', 'acme.zenonerp.test', '/api/v1/bootstrap', [], $cookie, ['X-Company-Id' => (string) $foreignId]),
        403,
        'forbidden',
    );
});

it('gives a non-admin core-enabled user their real, sorted permission names', function () {
    $tenant = createTenant('acme');
    installModule('core');
    enableModule('core', $tenant);

    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(function () use ($user) {
        Company::query()->where('is_default', true)->firstOrFail()->users()->attach($user);
        $user->givePermissionTo(['core.settings.view', 'core.companies.view']);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/bootstrap', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.permissions', ['core.companies.view', 'core.settings.view'])
        ->assertJsonPath('data.current_company_id', fn ($id) => is_int($id));
});
