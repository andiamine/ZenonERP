<?php

use App\Foundation\Company\Contracts\CompanyResolver;
use App\Foundation\Company\CurrentCompany;
use App\Foundation\Company\SetCurrentCompany;
use App\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
 * The zenon/core module (which will bind Contracts\CompanyResolver in production) doesn't
 * exist until a later Phase 5 task, so SetCurrentCompany is exercised here in isolation
 * against ad-hoc routes (the ErrorEnvelopeTest / PermissionsTest pattern).
 *
 * `zenon.kernel_module` exists purely for testability (see config/zenon.php): production
 * always gates on the real "core" alias, but tests point it at the DummyCore fixture
 * (alias "dummycore") so the positive (kernel-enabled) path is reachable today.
 */
beforeEach(function () {
    Route::middleware([
        'api',
        InitializeTenancyBySubdomain::class,
        PreventAccessFromCentralDomains::class,
        'auth:sanctum',
        SetCurrentCompany::class,
    ])->prefix('api')->get('/v1/_test/company', fn () => response()->json([
        'data' => ['company_id' => app(CurrentCompany::class)->id()],
    ]));

    // No auth guard here — proves SetCurrentCompany passes through on its own when
    // $request->user() is null, independent of any route-level auth middleware.
    Route::middleware([
        'api',
        InitializeTenancyBySubdomain::class,
        PreventAccessFromCentralDomains::class,
        SetCurrentCompany::class,
    ])->prefix('api')->get('/v1/_test/company-open', fn () => response()->json([
        'data' => ['company_id' => app(CurrentCompany::class)->id()],
    ]));
});

/**
 * @param  list<int>  $companyIds
 */
function bindFakeCompanyResolver(array $companyIds, ?int $default): void
{
    app()->instance(CompanyResolver::class, new class($companyIds, $default) implements CompanyResolver
    {
        public function __construct(private array $companyIds, private ?int $default) {}

        public function companyIdsFor(Authenticatable $user): array
        {
            return $this->companyIds;
        }

        public function defaultCompanyIdFor(Authenticatable $user): ?int
        {
            return $this->default;
        }
    });
}

/** Enables the DummyCore fixture as the gating "kernel" module for the given tenant. */
function enableKernelFixture(Tenant $tenant): void
{
    config(['zenon.kernel_module' => 'dummycore']);
    installModule('dummycore');
    enableModule('dummycore', $tenant);
}

it('sets CurrentCompany from a valid X-Company-Id header', function () {
    $acme = createTenant('acme');
    enableKernelFixture($acme);
    tenantUser($acme, ['email' => 'user@acme.test']);
    bindFakeCompanyResolver([1, 2], 1);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/_test/company', [], $cookie, ['X-Company-Id' => '2'])
        ->assertOk()
        ->assertJsonPath('data.company_id', 2);
});

it('rejects a company id outside the user\'s assigned companies with the forbidden envelope', function () {
    $acme = createTenant('acme');
    enableKernelFixture($acme);
    tenantUser($acme, ['email' => 'user@acme.test']);
    bindFakeCompanyResolver([1, 2], 1);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    $response = statefulJson('get', 'acme.zenonerp.test', '/api/v1/_test/company', [], $cookie, ['X-Company-Id' => '99']);

    assertErrorEnvelope($response, 403, 'forbidden');
});

it('rejects a non-numeric or non-positive X-Company-Id header', function () {
    $acme = createTenant('acme');
    enableKernelFixture($acme);
    tenantUser($acme, ['email' => 'user@acme.test']);
    bindFakeCompanyResolver([1, 2], 1);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    foreach (['not-a-number', '0', '-1', '1.5'] as $invalid) {
        assertErrorEnvelope(
            statefulJson('get', 'acme.zenonerp.test', '/api/v1/_test/company', [], $cookie, ['X-Company-Id' => $invalid]),
            403,
            'forbidden',
        );
    }
});

it('falls back to the resolver default company id when no header is sent', function () {
    $acme = createTenant('acme');
    enableKernelFixture($acme);
    tenantUser($acme, ['email' => 'user@acme.test']);
    bindFakeCompanyResolver([1, 2], 1);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/_test/company', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.company_id', 1);
});

it('passes through with CurrentCompany null when no CompanyResolver is bound', function () {
    $acme = createTenant('acme');
    enableKernelFixture($acme);
    tenantUser($acme, ['email' => 'user@acme.test']);
    // Deliberately no bindFakeCompanyResolver() call.

    // Since Task 5, zenon/core is globally active for the whole test suite (real modules'
    // providers register on every boot, mirroring production — CLAUDE.md §5) and its
    // CoreServiceProvider unconditionally binds CompanyResolver. Force the binding off to
    // exercise this defensive branch in isolation: SetCurrentCompany still gates on
    // `zenon.kernel_module` being enabled for the CURRENT tenant (the dummycore fixture
    // here, not core), so this scenario remains reachable in principle (e.g. a
    // misconfigured kernel_module pointing at a module that never binds the port).
    app()->offsetUnset(CompanyResolver::class);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/_test/company', [], $cookie, ['X-Company-Id' => '2'])
        ->assertOk()
        ->assertJsonPath('data.company_id', null);
});

it('passes through with CurrentCompany null when unauthenticated', function () {
    $acme = createTenant('acme');
    enableKernelFixture($acme);
    bindFakeCompanyResolver([1, 2], 1);

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/_test/company-open')
        ->assertOk()
        ->assertJsonPath('data.company_id', null);
});
