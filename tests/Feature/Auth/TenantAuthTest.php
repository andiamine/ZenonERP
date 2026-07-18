<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenant = createTenant('acme');
    tenantUser($this->tenant, ['email' => 'admin@acme.test']);
});

it('logs in with valid credentials and stores the session in the tenant DB', function () {
    [$response, $cookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'email']])
        ->assertJsonPath('data.email', 'admin@acme.test');

    expect($cookie)->not->toBeNull()
        ->and($this->tenant->run(fn () => DB::table('sessions')->count()))->toBe(1)
        ->and(DB::table('sessions')->count())->toBe(0); // central sessions untouched
});

it('rejects invalid credentials with the 422 envelope', function () {
    $response = statefulJson('post', 'acme.zenonerp.test', '/api/v1/auth/login', [
        'email' => 'admin@acme.test',
        'password' => 'wrong',
    ]);

    assertErrorEnvelope($response, 422, 'validation_error')
        ->assertJsonStructure(['error' => ['errors' => ['email']]]);
});

it('rate limits login after five failed attempts, even with valid credentials', function () {
    foreach (range(1, 5) as $attempt) {
        statefulJson('post', 'acme.zenonerp.test', '/api/v1/auth/login', [
            'email' => 'admin@acme.test',
            'password' => 'wrong',
        ])->assertUnprocessable();
    }

    $response = statefulJson('post', 'acme.zenonerp.test', '/api/v1/auth/login', [
        'email' => 'admin@acme.test',
        'password' => 'password',
    ]);

    assertErrorEnvelope($response, 422, 'validation_error');
});

it('returns the authenticated user on me', function () {
    [, $cookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/auth/me', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@acme.test');
});

it('rejects me without a session cookie', function () {
    $response = statefulJson('get', 'acme.zenonerp.test', '/api/v1/auth/me');

    assertErrorEnvelope($response, 401, 'unauthenticated');
});

it('never sees the session on non-stateful requests, even with a valid cookie', function () {
    [, $cookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    // No Referer/Origin → Sanctum treats the request as non-frontend → no session starts.
    test()->flushHeaders();
    test()->flushCookieState();
    test()->withCredentials()
        ->withUnencryptedCookie((string) config('session.cookie'), (string) $cookie)
        ->getJson('http://acme.zenonerp.test/api/v1/auth/me')
        ->assertUnauthorized();
});

it('invalidates the session on logout', function () {
    [, $cookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    statefulJson('post', 'acme.zenonerp.test', '/api/v1/auth/logout', [], $cookie)->assertNoContent();

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/auth/me', [], $cookie)->assertUnauthorized();
});

it('issues the csrf cookie with the session stored in the tenant DB', function () {
    test()->flushCookieState();
    $response = test()->withHeader('Referer', 'http://acme.zenonerp.test')
        ->get('http://acme.zenonerp.test/sanctum/csrf-cookie');

    $response->assertNoContent();

    expect($response->getCookie('XSRF-TOKEN', decrypt: false))->not->toBeNull()
        ->and($this->tenant->run(fn () => DB::table('sessions')->count()))->toBe(1)
        ->and(DB::table('sessions')->count())->toBe(0); // NOT in the central DB
});
