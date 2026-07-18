<?php

use App\Models\CentralUser;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    CentralUser::factory()->create(['email' => 'ops@zenonerp.test']);
});

it('logs in a platform operator on the central domain', function () {
    [$response, $cookie] = loginOn('app.zenonerp.test', 'ops@zenonerp.test');

    $response->assertOk()->assertJsonPath('data.email', 'ops@zenonerp.test');

    expect($cookie)->not->toBeNull()
        ->and(DB::table('sessions')->count())->toBe(1); // central DB session

    statefulJson('get', 'app.zenonerp.test', '/api/v1/auth/me', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.email', 'ops@zenonerp.test');
});

it('rejects central me without a cookie', function () {
    $response = statefulJson('get', 'app.zenonerp.test', '/api/v1/auth/me');

    assertErrorEnvelope($response, 401, 'unauthenticated');
});

it('invalidates the central session on logout', function () {
    [, $cookie] = loginOn('app.zenonerp.test', 'ops@zenonerp.test');

    statefulJson('post', 'app.zenonerp.test', '/api/v1/auth/logout', [], $cookie)->assertNoContent();

    statefulJson('get', 'app.zenonerp.test', '/api/v1/auth/me', [], $cookie)->assertUnauthorized();
});

it('rejects a central operator cookie on tenant hosts', function () {
    $tenant = createTenant('acme');
    tenantUser($tenant, ['email' => 'admin@acme.test']);

    [, $cookie] = loginOn('app.zenonerp.test', 'ops@zenonerp.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/auth/me', [], $cookie)->assertUnauthorized();
});

it('exposes no central auth endpoints on tenant hosts', function () {
    createTenant('acme');

    // Tenant hosts resolve the TENANT auth routes; a central operator's credentials
    // don't exist in the tenant users table, so login fails there.
    statefulJson('post', 'acme.zenonerp.test', '/api/v1/auth/login', [
        'email' => 'ops@zenonerp.test',
        'password' => 'password',
    ])->assertUnprocessable();
});
