<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

beforeEach(function () {
    // Test-only gated route, wrapped exactly like tenant-api routes plus spatie's
    // permission middleware — the shape every module route will use.
    Route::middleware([
        'api',
        InitializeTenancyBySubdomain::class,
        PreventAccessFromCentralDomains::class,
        'auth:sanctum',
        'permission:reports.view',
    ])->prefix('api')->get('/v1/_test/reports', fn () => response()->json(['data' => 'ok']));
});

it('creates the permission tables in tenant DBs only', function () {
    $tenant = createTenant('acme');

    $tables = ['permissions', 'roles', 'model_has_permissions', 'model_has_roles', 'role_has_permissions'];

    foreach ($tables as $table) {
        expect($tenant->run(fn () => Schema::hasTable($table)))->toBeTrue("missing {$table} in tenant DB")
            ->and(Schema::hasTable($table))->toBeFalse("{$table} leaked into the central DB");
    }
});

it('gates routes on permissions and flips 403 to 200 after a grant', function () {
    $tenant = createTenant('acme');
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => Permission::create(['name' => 'reports.view', 'guard_name' => 'web']));

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    $denied = statefulJson('get', 'acme.zenonerp.test', '/api/v1/_test/reports', [], $cookie);
    assertErrorEnvelope($denied, 403, 'forbidden');

    $tenant->run(fn () => $user->givePermissionTo('reports.view'));

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/_test/reports', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data', 'ok');
});

it('isolates permission grants per tenant', function () {
    // Exercises PermissionCacheTenancyBootstrapper: the registrar's cached permission
    // data must not survive tenant transitions within one process.
    $acme = createTenant('acme');
    $beta = createTenant('beta');
    $acmeUser = tenantUser($acme, ['email' => 'user@acme.test']);
    tenantUser($beta, ['email' => 'user@beta.test']);

    foreach ([$acme, $beta] as $tenant) {
        $tenant->run(fn () => Permission::create(['name' => 'reports.view', 'guard_name' => 'web']));
    }

    $acme->run(fn () => $acmeUser->givePermissionTo('reports.view')); // acme only

    [, $acmeCookie] = loginOn('acme.zenonerp.test', 'user@acme.test');
    [, $betaCookie] = loginOn('beta.zenonerp.test', 'user@beta.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/_test/reports', [], $acmeCookie)->assertOk();
    statefulJson('get', 'beta.zenonerp.test', '/api/v1/_test/reports', [], $betaCookie)->assertForbidden();
});
