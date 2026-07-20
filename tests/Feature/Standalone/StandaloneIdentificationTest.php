<?php

use App\Foundation\Installer\InstallerState;
use App\Foundation\Tenancy\Actions\CreateTenant;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Phase 8 Task 3: mode-aware tenancy identification. Standalone mode identifies the
 * single tenant by a full-domain `domains` row (the installed host, e.g.
 * "erp.example.test") via stock InitializeTenancyByDomain, reached through the new
 * InitializeTenancyByMode delegating middleware — NOT "any host is THE tenant"
 * (Host-header spoofing risk). Saas keeps subdomain identification unchanged; the rest
 * of the suite staying green is the saas regression proof (do not modify existing
 * tests). Config is read per-request (DeploymentMode::current(), CentralDomains
 * consumers), so no reboot is needed between tests.
 *
 * Phase 8 Task 5 added RedirectIfNotInstalled, which now intercepts every 'web'-group
 * request in standalone mode until the installer lock exists — these fixtures model an
 * already-provisioned tenant (in production that can't exist without the wizard having
 * run), so beforeEach marks the (temp, per-test) lock installed up front. This does not
 * affect the api-only tests below (routes/tenant-api.php sits outside the web group).
 */
beforeEach(function () {
    $this->installerLockPath = storage_path('framework/testing/installer-'.uniqid().'.lock');

    config([
        'zenon.mode' => 'standalone',
        'tenancy.central_domains' => [],
        'zenon.installer.lock_path' => $this->installerLockPath,
    ]);

    app(InstallerState::class)->markInstalled();
});

afterEach(function () {
    File::delete($this->installerLockPath);
});

/**
 * Creates the standalone tenant + full-domain row directly (bypassing CreateTenant,
 * which is standalone-guarded, and its subdomain-shaped validation) — mirrors what the
 * Task 4 installer action will do.
 */
function createStandaloneTenant(string $domain, string $id = 'default'): Tenant
{
    $tenant = Tenant::create(['id' => $id]);
    $tenant->domains()->create(['domain' => $domain]);

    return $tenant;
}

it('resolves a tenant by its full-domain row and runs requests in that tenant DB', function () {
    createStandaloneTenant('erp.example.test');

    $response = $this->getJson('http://erp.example.test/api/v1/ping');

    $response->assertOk()->assertJsonPath('data.tenant', 'default');
    expect($response->json('data.database'))->toContain('zenon_tenant_default');
});

it('serves the SPA shell and writes the session into the tenant DB on the installed host', function () {
    createStandaloneTenant('erp.example.test');

    $this->get('http://erp.example.test/')->assertOk()->assertViewIs('app');

    expect(Tenant::find('default')->run(fn () => DB::table('sessions')->count()))->toBe(1)
        ->and(DB::table('sessions')->count())->toBe(0);
});

it('404s an unknown host instead of throwing (proves InitializeTenancyByDomain::$onFail)', function () {
    createStandaloneTenant('erp.example.test');

    $this->getJson('http://ghost.example.test/api/v1/ping')->assertNotFound();
});

it('keeps a disabled module route 404 on the standalone host', function () {
    installModule('dummy');
    createStandaloneTenant('erp.example.test');

    $this->getJson('http://erp.example.test/api/v1/dummy/items')->assertNotFound();
});

it('404s signup on the standalone tenant host (central routes never registered for it)', function () {
    createStandaloneTenant('erp.example.test');

    $this->postJson('http://erp.example.test/api/v1/signup', ['subdomain' => 'acme'])
        ->assertNotFound();
});

it('refuses zenon:tenant:create in standalone mode, with no side effects', function () {
    $this->artisan('zenon:tenant:create', ['subdomain' => 'acme'])->assertFailed();

    expect(Tenant::query()->where('id', 'acme')->exists())->toBeFalse();
});

it('throws a clear LogicException from the CreateTenant action directly', function () {
    expect(fn () => app(CreateTenant::class)->handle('acme'))
        ->toThrow(LogicException::class, 'standalone');
});
