<?php

use Illuminate\Support\Facades\DB;

/**
 * The SPA catch-all (routes/web.php) serves app.blade.php on every non-reserved GET
 * path, on tenant and central hosts alike — while reserved prefixes keep their real
 * behavior (JSON 404 envelope for api/*, 404 for missing assets, health endpoint).
 */
it('serves the SPA shell at the tenant root', function () {
    createTenant('acme');

    test()->get('http://acme.zenonerp.test/')
        ->assertOk()
        ->assertViewIs('app')
        ->assertSee('id="root"', false);
});

it('serves the shell for deep SPA paths', function () {
    createTenant('acme');

    test()->get('http://acme.zenonerp.test/sales/orders/1')
        ->assertOk()
        ->assertViewIs('app');
});

it('serves the shell on central hosts', function () {
    test()->get('http://app.zenonerp.test/login')
        ->assertOk()
        ->assertViewIs('app');
});

it('writes the shell session into the tenant DB, not the central DB', function () {
    $tenant = createTenant('acme');

    test()->get('http://acme.zenonerp.test/')->assertOk();

    expect($tenant->run(fn () => DB::table('sessions')->count()))->toBe(1)
        ->and(DB::table('sessions')->count())->toBe(0);
});

it('keeps unknown api paths on the JSON 404 envelope', function () {
    createTenant('acme');

    $response = test()->getJson('http://acme.zenonerp.test/api/v1/nonexistent');

    assertErrorEnvelope($response, 404, 'not_found');
});

it('does not swallow reserved prefixes', function () {
    createTenant('acme');

    test()->get('http://acme.zenonerp.test/build/missing.js')->assertNotFound();
    test()->get('http://acme.zenonerp.test/sanctum/unknown')->assertNotFound();
    test()->get('http://acme.zenonerp.test/api')->assertNotFound();
    test()->get('http://acme.zenonerp.test/up')->assertOk();
});

it('still serves paths that merely start with a reserved word', function () {
    createTenant('acme');

    // "uploads" is not "up", "apidocs" is not "api/" — the lookahead is segment-precise.
    test()->get('http://acme.zenonerp.test/uploads')->assertOk()->assertViewIs('app');
    test()->get('http://acme.zenonerp.test/apidocs')->assertOk()->assertViewIs('app');
});

it('404s on unknown tenant subdomains', function () {
    createTenant('acme');

    test()->get('http://ghost.zenonerp.test/')->assertNotFound();
});
