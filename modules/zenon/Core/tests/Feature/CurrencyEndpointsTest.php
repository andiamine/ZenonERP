<?php

use Modules\Core\Models\Currency;

it('lists, shows, creates, updates and deletes currencies for a user granted the relevant permissions', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(function () use ($user) {
        $user->givePermissionTo([
            'core.currencies.view', 'core.currencies.create', 'core.currencies.update', 'core.currencies.delete',
        ]);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/currencies', [], $cookie)
        ->assertOk()
        ->assertJsonCount(4, 'data'); // seeded USD/EUR/GBP/MAD

    $created = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/currencies', [
        'code' => 'JPY', 'name' => 'Japanese Yen', 'decimal_places' => 0,
    ], $cookie)
        ->assertCreated()
        ->assertJsonPath('data.code', 'JPY')
        ->assertJsonPath('data.decimal_places', 0)
        ->assertJsonPath('data.active', true);

    $currencyId = $created->json('data.id');

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/currencies/{$currencyId}", [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.name', 'Japanese Yen');

    statefulJson('patch', 'acme.zenonerp.test', "/api/v1/core/currencies/{$currencyId}", ['name' => 'Yen'], $cookie)
        ->assertOk()
        ->assertJsonPath('data.name', 'Yen');

    statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/currencies/{$currencyId}", [], $cookie)
        ->assertNoContent();

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/currencies/{$currencyId}", [], $cookie)
        ->assertNotFound();
});

it('rejects creating a currency with a duplicate code with a 422 envelope', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.currencies.create'));

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    assertErrorEnvelope(
        statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/currencies', [
            'code' => 'USD', 'name' => 'Duplicate Dollar',
        ], $cookie),
        422,
        'validation_error',
    );
});

it('blocks deleting a currency referenced by a company with a 409 envelope', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.currencies.delete'));

    $usdId = $tenant->run(fn () => Currency::query()->where('code', 'USD')->firstOrFail()->id);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    // MAIN (the seeded default company) has currency_code = 'USD'.
    assertErrorEnvelope(
        statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/currencies/{$usdId}", [], $cookie),
        409,
        'conflict',
    );
});

it('denies a user with no core.currencies permission with the forbidden envelope', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']); // no grants at all

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    assertErrorEnvelope(
        statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/currencies', [], $cookie),
        403,
        'forbidden',
    );
});
