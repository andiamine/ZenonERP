<?php

use App\Foundation\Frontend\GeneratedModuleRegistry;
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
