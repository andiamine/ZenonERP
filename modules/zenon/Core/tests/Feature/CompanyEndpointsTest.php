<?php

use App\Models\Tenant;
use Modules\Core\Models\Company;
use Spatie\Permission\Models\Role;

/** Boots a tenant with zenon/core installed + enabled (companies/settings/currencies/teams tables, MAIN company, admin role). */
function bootCoreTenant(string $subdomain = 'acme'): Tenant
{
    $tenant = createTenant($subdomain);
    installModule('core');
    enableModule('core', $tenant);

    return $tenant;
}

it('lists, shows, creates, updates and deletes companies for a user granted the relevant permissions', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(function () use ($user) {
        $user->givePermissionTo([
            'core.companies.view', 'core.companies.create', 'core.companies.update', 'core.companies.delete',
        ]);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/companies', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.0.code', 'MAIN')
        ->assertJsonPath('data.0.is_default', true);

    $created = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/companies', [
        'name' => 'Beta Co', 'code' => 'BETA', 'currency_code' => 'USD',
    ], $cookie)
        ->assertCreated()
        ->assertJsonPath('data.code', 'BETA')
        ->assertJsonPath('data.is_default', false);

    $companyId = $created->json('data.id');

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/companies/{$companyId}", [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.name', 'Beta Co');

    statefulJson('patch', 'acme.zenonerp.test', "/api/v1/core/companies/{$companyId}", ['name' => 'Beta Co Renamed'], $cookie)
        ->assertOk()
        ->assertJsonPath('data.name', 'Beta Co Renamed');

    statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/companies/{$companyId}", [], $cookie)
        ->assertNoContent();

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/companies/{$companyId}", [], $cookie)
        ->assertNotFound();
});

it('attaches the creating user to the new company so it surfaces in their bootstrap companies', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'creator@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.companies.create'));

    [, $cookie] = loginOn('acme.zenonerp.test', 'creator@acme.test');

    $created = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/companies', [
        'name' => 'Gamma Co', 'code' => 'GAMMA', 'currency_code' => 'USD',
    ], $cookie)->assertCreated();

    $companyId = $created->json('data.id');

    // The pivot row exists — the creator is a member of the company they just created.
    $tenant->run(function () use ($user, $companyId) {
        expect(Company::findOrFail($companyId)->users()->whereKey($user->getKey())->exists())->toBeTrue();
    });

    // And it appears in their membership-filtered bootstrap payload (the switcher's source).
    $companies = statefulJson('get', 'acme.zenonerp.test', '/api/v1/bootstrap', [], $cookie)
        ->assertOk()
        ->json('data.companies');

    expect(collect($companies)->pluck('code')->all())->toContain('GAMMA');
});

it('flips 403 to 200 for GET /companies after granting core.companies.view via the roles endpoint (§12 verify criterion)', function () {
    $tenant = bootCoreTenant();

    $fresh = tenantUser($tenant, ['email' => 'fresh@acme.test']);
    $admin = tenantUser($tenant, ['email' => 'admin@acme.test']);
    $tenant->run(function () use ($admin) {
        $admin->assignRole('admin'); // seeded by CoreDatabaseSeeder, Gate::before bypasses every permission check
    });

    [, $freshCookie] = loginOn('acme.zenonerp.test', 'fresh@acme.test');
    [, $adminCookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    // Half 1: no grant yet — 403.
    assertErrorEnvelope(
        statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/companies', [], $freshCookie),
        403,
        'forbidden',
    );

    $tenant->run(function () {
        Role::create(['name' => 'viewer', 'guard_name' => 'web'])->givePermissionTo('core.companies.view');
    });

    statefulJson('put', 'acme.zenonerp.test', "/api/v1/core/users/{$fresh->id}/roles", ['roles' => ['viewer']], $adminCookie)
        ->assertOk()
        ->assertJsonPath('data.roles', ['viewer']);

    // Half 2: same request, same fresh cookie, now 200 — the role assignment alone flipped it.
    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/companies', [], $freshCookie)
        ->assertOk();
});

it('blocks deleting the default company with a 409 envelope', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.companies.delete'));

    $mainId = $tenant->run(fn () => Company::query()->where('is_default', true)->firstOrFail()->id);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    assertErrorEnvelope(
        statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/companies/{$mainId}", [], $cookie),
        409,
        'conflict',
    );
});

it('blocks deleting the last remaining company with a 409 envelope even once it is no longer flagged default', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.companies.delete'));

    // Force the is_default guard out of the way so the delete attempt exercises the
    // SEPARATE "last company" invariant (Company::count() <= 1) in isolation.
    $mainId = $tenant->run(function () {
        $main = Company::query()->where('is_default', true)->firstOrFail();
        $main->update(['is_default' => false]);

        return $main->id;
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    assertErrorEnvelope(
        statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/companies/{$mainId}", [], $cookie),
        409,
        'conflict',
    );
});

it('filters companies by partial name match', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(function () use ($user) {
        $user->givePermissionTo(['core.companies.view', 'core.companies.create']);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/companies', [
        'name' => 'Zeta Corp', 'code' => 'ZETA', 'currency_code' => 'USD',
    ], $cookie)->assertCreated();

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/companies?filter[name]=Zet', [], $cookie)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'ZETA');
});

it('rejects an unknown filter key with the §8 bad_request envelope', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.companies.view'));

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    assertErrorEnvelope(
        statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/companies?filter[bogus]=x', [], $cookie),
        400,
        'bad_request',
    );
});
