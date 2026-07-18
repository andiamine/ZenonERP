<?php

use Modules\Core\Models\Company;

it('lists, shows, creates, updates and deletes teams for a user granted the relevant permissions', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $tenant->run(function () use ($actor) {
        $actor->givePermissionTo(['core.teams.view', 'core.teams.create', 'core.teams.update', 'core.teams.delete']);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/teams', [], $cookie)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $created = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/teams', ['name' => 'Sales Team'], $cookie)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Sales Team')
        ->assertJsonPath('data.active', true);

    $teamId = $created->json('data.id');

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/teams/{$teamId}", [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.name', 'Sales Team');

    statefulJson('patch', 'acme.zenonerp.test', "/api/v1/core/teams/{$teamId}", ['name' => 'Sales Team Renamed'], $cookie)
        ->assertOk()
        ->assertJsonPath('data.name', 'Sales Team Renamed');

    statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/teams/{$teamId}", [], $cookie)
        ->assertNoContent();

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/teams/{$teamId}", [], $cookie)
        ->assertNotFound();
});

it('syncs team members via PUT and reflects them when included', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $member = tenantUser($tenant, ['email' => 'member@acme.test']);
    $tenant->run(fn () => $actor->givePermissionTo(['core.teams.view', 'core.teams.create', 'core.teams.update']));

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    $teamId = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/teams', ['name' => 'Sales Team'], $cookie)
        ->assertCreated()->json('data.id');

    statefulJson('put', 'acme.zenonerp.test', "/api/v1/core/teams/{$teamId}/users", ['user_ids' => [$member->id]], $cookie)
        ->assertOk()
        ->assertJsonCount(1, 'data.users')
        ->assertJsonPath('data.users.0.email', 'member@acme.test');
});

/**
 * First real HTTP-level assertion of Foundation's CompanyScope (CLAUDE.md §9.3): a
 * NULL company_id row is shared/visible to every company; a company-specific row is
 * visible only under its own company header.
 */
it('scopes teams by company: A sees A + shared, B sees B + shared, never the other company\'s team', function () {
    $tenant = bootCoreTenant();
    $actor = tenantUser($tenant, ['email' => 'actor@acme.test']);
    $tenant->run(function () use ($actor) {
        $actor->givePermissionTo(['core.teams.view', 'core.teams.create', 'core.companies.create', 'core.companies.update']);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'actor@acme.test');

    $companyAId = $tenant->run(function () use ($actor) {
        $main = Company::query()->where('is_default', true)->firstOrFail();
        $main->users()->attach($actor);

        return $main->id;
    });

    $companyBId = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/companies', [
        'name' => 'Beta Co', 'code' => 'BETA', 'currency_code' => 'USD',
    ], $cookie)->assertCreated()->json('data.id');

    statefulJson('put', 'acme.zenonerp.test', "/api/v1/core/companies/{$companyBId}/users", ['user_ids' => [$actor->id]], $cookie)
        ->assertOk();

    statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/teams', ['name' => 'Team A', 'company_id' => $companyAId], $cookie)
        ->assertCreated();
    statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/teams', ['name' => 'Team B', 'company_id' => $companyBId], $cookie)
        ->assertCreated();
    statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/teams', ['name' => 'Team Shared'], $cookie)
        ->assertCreated();

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/teams', [], $cookie, ['X-Company-Id' => (string) $companyAId])
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->tap(fn ($response) => expect($response->json('data.*.name'))->toEqualCanonicalizing(['Team A', 'Team Shared']));

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/teams', [], $cookie, ['X-Company-Id' => (string) $companyBId])
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->tap(fn ($response) => expect($response->json('data.*.name'))->toEqualCanonicalizing(['Team B', 'Team Shared']));
});
