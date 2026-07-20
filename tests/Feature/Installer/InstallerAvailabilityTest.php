<?php

use App\Foundation\Installer\InstallerState;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;

/**
 * Phase 8 Task 5: the installer availability gate. EnsureInstallerAvailable guards every
 * /install* route (registered outside web/api in bootstrap/app.php — no session exists, so
 * unsafe methods are checked for same-origin via Origin/Referer instead of a CSRF token):
 * 404 unless standalone mode AND not yet installed; 403 on an unsafe request that isn't
 * verifiably same-origin. RedirectIfNotInstalled (prepended to the web group before
 * tenancy init) sends every other path to /install on a fresh, unprovisioned standalone
 * extract, and gets out of the way once installed or in saas mode.
 *
 * lock_path is pointed at a throwaway per-test file so this suite never touches a real
 * installed.lock.
 */
beforeEach(function () {
    $this->lockPath = storage_path('framework/testing/installer-'.uniqid().'.lock');
    config(['zenon.installer.lock_path' => $this->lockPath]);
});

afterEach(function () {
    File::delete($this->lockPath);
});

it('404s /install in saas mode', function () {
    test()->get('http://app.zenonerp.test/install')->assertNotFound();
});

it('serves the installer stub in standalone mode while unlocked', function () {
    config(['zenon.mode' => 'standalone']);

    test()->get('http://erp.example.test/install')->assertOk();
});

it('404s the installer once locked', function () {
    config(['zenon.mode' => 'standalone']);
    app(InstallerState::class)->markInstalled();

    test()->get('http://erp.example.test/install')->assertNotFound();
});

it('rejects an unsafe request with a cross-origin Origin header', function () {
    config(['zenon.mode' => 'standalone']);

    test()->withHeaders(['Origin' => 'http://evil.test'])
        ->post('http://erp.example.test/install/api/ping')
        ->assertForbidden();
});

it('rejects an unsafe request with neither Origin nor Referer', function () {
    config(['zenon.mode' => 'standalone']);

    test()->post('http://erp.example.test/install/api/ping')->assertForbidden();
});

it('falls back to the Referer host when Origin is absent, rejecting a cross-origin Referer', function () {
    config(['zenon.mode' => 'standalone']);

    test()->withHeaders(['Referer' => 'http://evil.test/somewhere'])
        ->post('http://erp.example.test/install/api/ping')
        ->assertForbidden();
});

it('passes the same-origin gate for a same-origin Origin header', function () {
    config(['zenon.mode' => 'standalone']);

    test()->withHeaders(['Origin' => 'http://erp.example.test'])
        ->post('http://erp.example.test/install/api/ping')
        ->assertNoContent();
});

it('passes the same-origin gate via Referer when Origin is absent', function () {
    config(['zenon.mode' => 'standalone']);

    test()->withHeaders(['Referer' => 'http://erp.example.test/install'])
        ->post('http://erp.example.test/install/api/ping')
        ->assertNoContent();
});

it('redirects / to /install in standalone mode while unlocked', function () {
    config(['zenon.mode' => 'standalone', 'tenancy.central_domains' => []]);

    test()->get('http://erp.example.test/')
        ->assertRedirect('http://erp.example.test/install');
});

it('serves the SPA on / in standalone mode once installed', function () {
    config(['zenon.mode' => 'standalone', 'tenancy.central_domains' => []]);
    app(InstallerState::class)->markInstalled();

    $tenant = Tenant::create(['id' => 'default']);
    $tenant->domains()->create(['domain' => 'erp.example.test']);

    test()->get('http://erp.example.test/')->assertOk()->assertViewIs('app');
});

it('serves the SPA on / unchanged in saas mode', function () {
    test()->get('http://app.zenonerp.test/')->assertOk()->assertViewIs('app');
});
