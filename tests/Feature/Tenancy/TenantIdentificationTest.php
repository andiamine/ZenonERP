<?php

it('reports the tenant and its database on a tenant subdomain', function () {
    createTenant('acme');

    $response = $this->getJson('http://acme.zenonerp.test/api/v1/ping');

    $response->assertOk()
        ->assertJsonPath('data.tenant', 'acme');

    expect($response->json('data.database'))->toContain('zenon_tenant_acme');
});

it('hits a different database per tenant subdomain', function () {
    createTenant('acme');
    createTenant('beta');

    $acme = $this->getJson('http://acme.zenonerp.test/api/v1/ping')->assertOk();
    $beta = $this->getJson('http://beta.zenonerp.test/api/v1/ping')->assertOk();

    expect($acme->json('data.database'))->toContain('zenon_tenant_acme')
        ->and($beta->json('data.database'))->toContain('zenon_tenant_beta')
        ->and($acme->json('data.database'))->not->toBe($beta->json('data.database'));
});

it('returns 404 for tenant routes on the central domain', function () {
    createTenant('acme');

    $this->getJson('http://app.zenonerp.test/api/v1/ping')->assertNotFound();
});

it('returns 404 for an unknown subdomain', function () {
    $this->getJson('http://ghost.zenonerp.test/api/v1/ping')->assertNotFound();
});
