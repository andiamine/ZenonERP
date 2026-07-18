<?php

use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Company;
use Spatie\Permission\Models\Role;

it('lists, shows, creates, updates and deletes users for a user granted the relevant permissions', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $tenant->run(function () use ($actor) {
        $actor->givePermissionTo(['core.users.view', 'core.users.create', 'core.users.update', 'core.users.delete']);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/users', [], $cookie)
        ->assertOk()
        ->assertJsonCount(1, 'data'); // just $actor so far

    $created = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/users', [
        'name' => 'New Guy', 'email' => 'newguy@acme.test', 'password' => 'password123',
    ], $cookie)
        ->assertCreated()
        ->assertJsonPath('data.email', 'newguy@acme.test');

    $newUserId = $created->json('data.id');

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/users/{$newUserId}", [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.name', 'New Guy');

    statefulJson('patch', 'acme.zenonerp.test', "/api/v1/core/users/{$newUserId}", ['name' => 'New Guy Renamed'], $cookie)
        ->assertOk()
        ->assertJsonPath('data.name', 'New Guy Renamed');

    statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/users/{$newUserId}", [], $cookie)
        ->assertNoContent();

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/users/{$newUserId}", [], $cookie)
        ->assertNotFound();
});

it('auto-attaches a newly created user to the default company', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $tenant->run(fn () => $actor->givePermissionTo('core.users.create'));

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    $newUserId = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/users', [
        'name' => 'New Guy', 'email' => 'newguy@acme.test', 'password' => 'password123',
    ], $cookie)->assertCreated()->json('data.id');

    $tenant->run(function () use ($newUserId) {
        $mainId = Company::query()->where('is_default', true)->firstOrFail()->id;

        expect(DB::table('company_user')->where('user_id', $newUserId)->where('company_id', $mainId)->exists())->toBeTrue();
    });
});

it('blocks a user from deleting their own account with a 409 envelope', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $tenant->run(fn () => $actor->givePermissionTo('core.users.delete'));

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    assertErrorEnvelope(
        statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/users/{$actor->id}", [], $cookie),
        409,
        'conflict',
    );
});

it('filters users by role name and includes roles when requested', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $manager = tenantUser($tenant, ['email' => 'manager@acme.test']);
    $tenant->run(function () use ($actor, $manager) {
        $actor->givePermissionTo('core.users.view');
        Role::create(['name' => 'manager', 'guard_name' => 'web']);
        $manager->assignRole('manager');
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/users?filter[role]=manager', [], $cookie)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.email', 'manager@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/users?include=roles&filter[email]=manager', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.0.roles', ['manager']);
});
