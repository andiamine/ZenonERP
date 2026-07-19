<?php

use Illuminate\Support\Facades\File;

/**
 * ModuleAssetController streams modules/thirdparty/{folder}/dist/{path} (CLAUDE.md §7
 * remote loading) — no real addon needed on disk: zenon.thirdparty_path is a config
 * indirection (the test seam) redirected here at a temp fixture dir shaped like a
 * prebuilt addon's dist/ output.
 */
beforeEach(function () {
    $this->thirdpartyPath = storage_path('framework/testing/thirdparty-'.uniqid());

    File::ensureDirectoryExists($this->thirdpartyPath.'/Demo/dist/assets');
    File::put($this->thirdpartyPath.'/Demo/dist/remoteEntry.js', 'export default {};');
    File::put($this->thirdpartyPath.'/Demo/dist/assets/chunk-ABC123.js', 'console.log(1);');
    File::put($this->thirdpartyPath.'/Demo/dist/assets/style-DEF456.css', 'body { color: red; }');
    File::put($this->thirdpartyPath.'/Demo/dist/mf-manifest.json', '{}');
    File::ensureDirectoryExists($this->thirdpartyPath.'/NoDist'); // installed folder, never built

    config(['zenon.thirdparty_path' => $this->thirdpartyPath]);
});

afterEach(function () {
    File::deleteDirectory($this->thirdpartyPath);
});

it('serves remoteEntry.js as no-cache javascript', function () {
    $response = test()->get('http://app.zenonerp.test/modules/thirdparty/Demo/dist/remoteEntry.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/javascript');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
});

it('serves a hashed asset chunk as immutable javascript', function () {
    $response = test()->get('http://app.zenonerp.test/modules/thirdparty/Demo/dist/assets/chunk-ABC123.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/javascript');
    expect($response->headers->get('Cache-Control'))->toContain('immutable');
});

it('serves a css asset with the right content type', function () {
    $response = test()->get('http://app.zenonerp.test/modules/thirdparty/Demo/dist/assets/style-DEF456.css');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/css');
    expect($response->headers->get('Cache-Control'))->toContain('immutable');
});

it('serves mf-manifest.json as no-cache json', function () {
    $response = test()->get('http://app.zenonerp.test/modules/thirdparty/Demo/dist/mf-manifest.json');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('application/json');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
});

it('404s for an unknown folder', function () {
    test()->get('http://app.zenonerp.test/modules/thirdparty/Ghost/dist/remoteEntry.js')->assertNotFound();
});

it('404s for an unknown file inside a real addon', function () {
    test()->get('http://app.zenonerp.test/modules/thirdparty/Demo/dist/nope.js')->assertNotFound();
});

it('404s for a folder that exists but was never built', function () {
    test()->get('http://app.zenonerp.test/modules/thirdparty/NoDist/dist/remoteEntry.js')->assertNotFound();
});

it('contains a literal traversal segment as a 404, never the target file', function () {
    $response = test()->get('http://app.zenonerp.test/modules/thirdparty/Demo/dist/../../../.env');

    $response->assertNotFound();
    expect($response->getContent())->not->toContain('APP_KEY');
});

it('contains a %2e%2e-encoded traversal segment as a 404', function () {
    // Laravel decodes %2e%2e BEFORE route matching — the where() regex alone cannot
    // stop it; ModuleAssetController's own ".." check is what must catch this.
    $response = test()->get('http://app.zenonerp.test/modules/thirdparty/Demo/dist/%2e%2e/%2e%2e/%2e%2e/.env');

    $response->assertNotFound();
    expect($response->getContent())->not->toContain('APP_KEY');
});

it('contains a backslash traversal variant as a 404', function () {
    // A literal backslash isn't valid raw URI syntax for the test HTTP client — use the
    // %5C-encoded form, which decodes to a backslash server-side before reaching the
    // controller's own backslash rejection.
    $response = test()->get('http://app.zenonerp.test/modules/thirdparty/Demo/dist/..%5C..%5C..%5C.env');

    $response->assertNotFound();
    expect($response->getContent())->not->toContain('APP_KEY');
});

it('never sets a session cookie on an asset response', function () {
    $response = test()->get('http://app.zenonerp.test/modules/thirdparty/Demo/dist/remoteEntry.js');

    $response->assertOk();
    expect($response->headers->get('Set-Cookie'))->toBeNull();
});

it('serves the same asset on a tenant subdomain host', function () {
    $response = test()->get('http://acme.zenonerp.test/modules/thirdparty/Demo/dist/remoteEntry.js');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/javascript');
    expect($response->headers->get('Set-Cookie'))->toBeNull();
});
