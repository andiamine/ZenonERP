<?php

// Phase 3 acceptance (CLAUDE.md §12): an acme session cookie is rejected on beta.
// Host-only cookies (SESSION_DOMAIN=null) mean browsers never even send it there;
// these tests replay the cookie manually to prove the SERVER also rejects it —
// sessions live in each tenant's own DB, so the id simply doesn't exist elsewhere.

beforeEach(function () {
    $this->acme = createTenant('acme');
    $this->beta = createTenant('beta');
    tenantUser($this->acme, ['email' => 'admin@acme.test']);
    tenantUser($this->beta, ['email' => 'admin@beta.test']);
});

it('rejects an acme session cookie replayed on beta', function () {
    [, $cookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    $replayed = statefulJson('get', 'beta.zenonerp.test', '/api/v1/auth/me', [], $cookie);
    assertErrorEnvelope($replayed, 401, 'unauthenticated');

    // Control: the same cookie is valid where it was issued.
    statefulJson('get', 'acme.zenonerp.test', '/api/v1/auth/me', [], $cookie)->assertOk();
});

it('rejects an acme session cookie replayed on the central domain', function () {
    [, $cookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    statefulJson('get', 'app.zenonerp.test', '/api/v1/auth/me', [], $cookie)->assertUnauthorized();
});

it('issues host-only session cookies', function () {
    [$response] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    $cookie = $response->getCookie((string) config('session.cookie'), decrypt: false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getDomain())->toBeNull(); // SESSION_DOMAIN=null → host-only
});

it('keeps interleaved tenant sessions fully isolated', function () {
    // Exercises SessionAuthTenancyBootstrapper: the session handler/guards must
    // rebuild per tenant transition, or acme's handler would serve beta.
    [, $acmeCookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');
    [, $betaCookie] = loginOn('beta.zenonerp.test', 'admin@beta.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/auth/me', [], $acmeCookie)
        ->assertOk()->assertJsonPath('data.email', 'admin@acme.test');
    statefulJson('get', 'beta.zenonerp.test', '/api/v1/auth/me', [], $betaCookie)
        ->assertOk()->assertJsonPath('data.email', 'admin@beta.test');

    statefulJson('get', 'beta.zenonerp.test', '/api/v1/auth/me', [], $acmeCookie)->assertUnauthorized();
    statefulJson('get', 'acme.zenonerp.test', '/api/v1/auth/me', [], $betaCookie)->assertUnauthorized();
});
