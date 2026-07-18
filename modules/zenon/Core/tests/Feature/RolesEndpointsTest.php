<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('lists, shows, creates, updates and deletes roles for a user granted the relevant permissions', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $tenant->run(function () use ($actor) {
        $actor->givePermissionTo(['core.roles.view', 'core.roles.create', 'core.roles.update', 'core.roles.delete']);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/roles', [], $cookie)
        ->assertOk()
        ->assertJsonCount(1, 'data') // just the seeded 'admin' role so far
        ->assertJsonPath('data.0.name', 'admin');

    $created = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/roles', ['name' => 'viewer'], $cookie)
        ->assertCreated()
        ->assertJsonPath('data.name', 'viewer');

    $roleId = $created->json('data.id');

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/roles/{$roleId}", [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.name', 'viewer');

    statefulJson('patch', 'acme.zenonerp.test', "/api/v1/core/roles/{$roleId}", ['name' => 'viewer-renamed'], $cookie)
        ->assertOk()
        ->assertJsonPath('data.name', 'viewer-renamed');

    statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/roles/{$roleId}", [], $cookie)
        ->assertNoContent();

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/roles/{$roleId}", [], $cookie)
        ->assertNotFound();
});

it('blocks deleting the admin role with a 409 envelope', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $tenant->run(fn () => $actor->givePermissionTo('core.roles.delete'));

    $adminRoleId = $tenant->run(fn () => Role::query()->where('name', 'admin')->firstOrFail()->id);

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    assertErrorEnvelope(
        statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/roles/{$adminRoleId}", [], $cookie),
        409,
        'conflict',
    );
});

it('syncs role permissions via PUT and reflects them on the role', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $tenant->run(fn () => $actor->givePermissionTo(['core.roles.view', 'core.roles.create', 'core.roles.update']));

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    $roleId = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/roles', ['name' => 'viewer'], $cookie)
        ->assertCreated()->json('data.id');

    statefulJson('put', 'acme.zenonerp.test', "/api/v1/core/roles/{$roleId}/permissions", [
        'permissions' => ['core.companies.view', 'core.settings.view'],
    ], $cookie)
        ->assertOk()
        ->assertJsonPath('data.permissions', ['core.companies.view', 'core.settings.view']);

    $tenant->run(function () use ($roleId) {
        $role = Role::query()->findOrFail($roleId);

        expect($role->permissions->pluck('name')->sort()->values()->all())
            ->toBe(['core.companies.view', 'core.settings.view']);
    });
});

it('returns the flat, synced permission list sorted by name', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $tenant->run(fn () => $actor->givePermissionTo('core.roles.view'));

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    $names = $tenant->run(fn () => Permission::query()->orderBy('name')->pluck('name')->all());

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/permissions', [], $cookie)
        ->assertOk()
        ->assertJsonCount(count($names), 'data') // all 23 core manifest permissions, synced on enable
        ->assertJsonPath('data.*.name', $names);
});
