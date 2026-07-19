<?php

use App\Models\Tenant;
use Modules\Core\Models\Company;
use Modules\Demo\Listeners\RecordCompanyDeletion;

/*
 * Phase 7 Task 5: the Demo third-party addon — the extension-proof consumer of Core's
 * Phase 7 hooks (CompanyApiResponse / CompanyDeleting / CompanyDeleted). Two-tenant
 * pattern mirrors tests/Feature/Hooks/HookHttpTest.php: acme has demo enabled, beta
 * only has core — every scenario proves demo's effects are per-tenant gated.
 */

/** Boots a tenant with zenon/core installed+enabled, optionally with demo too. */
function bootDemoTenant(string $subdomain, bool $withDemo): Tenant
{
    $tenant = createTenant($subdomain);
    installModule('core');
    enableModule('core', $tenant);

    if ($withDemo) {
        installModule('demo'); // auto-installs core (already installed — no-op)
        enableModule('demo', $tenant); // auto-enables core (already enabled — no-op)
    }

    return $tenant;
}

beforeEach(fn () => RecordCompanyDeletion::reset());

it('appends computed insight fields keyed by company id only for tenants with demo enabled', function () {
    $acme = bootDemoTenant('acme', withDemo: true);
    $beta = bootDemoTenant('beta', withDemo: false);

    $acmeUser = tenantUser($acme, ['email' => 'user@acme.test']);
    $acme->run(fn () => $acmeUser->givePermissionTo('core.companies.view'));
    $acmeCompany = $acme->run(fn () => Company::query()->where('is_default', true)->firstOrFail());

    $betaUser = tenantUser($beta, ['email' => 'user@beta.test']);
    $beta->run(fn () => $betaUser->givePermissionTo('core.companies.view'));

    [, $acmeCookie] = loginOn('acme.zenonerp.test', 'user@acme.test');
    [, $betaCookie] = loginOn('beta.zenonerp.test', 'user@beta.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/companies', [], $acmeCookie)
        ->assertOk()
        ->assertJsonPath("extra.{$acmeCompany->id}.computed_by", 'acme/demo')
        ->assertJsonPath("extra.{$acmeCompany->id}.name_length", strlen($acmeCompany->name))
        // DI-proof key (mirrors DummyDep's AddDummyComputedField): AddCompanyInsights
        // constructor-injects Illuminate\Contracts\Config\Repository and reads
        // zenon.platform_version into `platform` — proves HookBus resolves filters
        // through the container, not `new`.
        ->assertJsonPath("extra.{$acmeCompany->id}.platform", '1.0.0');

    $betaResponse = statefulJson('get', 'beta.zenonerp.test', '/api/v1/core/companies', [], $betaCookie)
        ->assertOk();
    expect($betaResponse->getContent())->toContain('"extra":{}');
});

it('vetoes deleting a LOCKED company only for tenants with demo enabled', function () {
    $acme = bootDemoTenant('acme', withDemo: true);
    $beta = bootDemoTenant('beta', withDemo: false);

    $acmeUser = tenantUser($acme, ['email' => 'user@acme.test']);
    $acme->run(fn () => $acmeUser->givePermissionTo('core.companies.delete'));
    $acmeLockedId = $acme->run(fn () => Company::factory()->create(['code' => 'LOCKED'])->id);

    $betaUser = tenantUser($beta, ['email' => 'user@beta.test']);
    $beta->run(fn () => $betaUser->givePermissionTo('core.companies.delete'));
    $betaLockedId = $beta->run(fn () => Company::factory()->create(['code' => 'LOCKED'])->id);

    [, $acmeCookie] = loginOn('acme.zenonerp.test', 'user@acme.test');
    [, $betaCookie] = loginOn('beta.zenonerp.test', 'user@beta.test');

    $response = statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/companies/{$acmeLockedId}", [], $acmeCookie);

    assertErrorEnvelope($response, 422, 'action_vetoed')
        ->assertJsonPath('error.code', 'demo.company_locked');

    $acme->run(fn () => expect(Company::find($acmeLockedId))->not->toBeNull());

    statefulJson('delete', 'beta.zenonerp.test', "/api/v1/core/companies/{$betaLockedId}", [], $betaCookie)
        ->assertNoContent();

    $beta->run(fn () => expect(Company::find($betaLockedId))->toBeNull());
});

it('records a normal company deletion only for tenants with demo enabled', function () {
    $acme = bootDemoTenant('acme', withDemo: true);
    $beta = bootDemoTenant('beta', withDemo: false);

    $acmeUser = tenantUser($acme, ['email' => 'user@acme.test']);
    $acme->run(fn () => $acmeUser->givePermissionTo('core.companies.delete'));
    [$acmeCompanyId, $acmeCompanyName] = $acme->run(function () {
        $company = Company::factory()->create(['code' => 'SIDE']);

        return [$company->id, $company->name];
    });

    $betaUser = tenantUser($beta, ['email' => 'user@beta.test']);
    $beta->run(fn () => $betaUser->givePermissionTo('core.companies.delete'));
    $betaCompanyId = $beta->run(fn () => Company::factory()->create(['code' => 'SIDE'])->id);

    [, $acmeCookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/companies/{$acmeCompanyId}", [], $acmeCookie)
        ->assertNoContent();

    expect(RecordCompanyDeletion::$deleted)->toBe([
        ['id' => $acmeCompanyId, 'code' => 'SIDE', 'name' => $acmeCompanyName],
    ]);

    RecordCompanyDeletion::reset();

    [, $betaCookie] = loginOn('beta.zenonerp.test', 'user@beta.test');

    statefulJson('delete', 'beta.zenonerp.test', "/api/v1/core/companies/{$betaCompanyId}", [], $betaCookie)
        ->assertNoContent();

    expect(RecordCompanyDeletion::$deleted)->toBe([]); // TenantGatedListener no-oped for beta
});

it('advertises the demo remote in bootstrap only for tenants with demo enabled', function () {
    $acme = bootDemoTenant('acme', withDemo: true);
    $beta = bootDemoTenant('beta', withDemo: false);

    tenantUser($acme, ['email' => 'user@acme.test']);
    tenantUser($beta, ['email' => 'user@beta.test']);

    [, $acmeCookie] = loginOn('acme.zenonerp.test', 'user@acme.test');
    [, $betaCookie] = loginOn('beta.zenonerp.test', 'user@beta.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/bootstrap', [], $acmeCookie)
        ->assertOk()
        ->assertJsonPath('data.remote_modules', [[
            'id' => 'demo',
            'url' => '/modules/thirdparty/Demo/dist/remoteEntry.js',
            'platform' => '^1.0',
        ]]);

    statefulJson('get', 'beta.zenonerp.test', '/api/v1/bootstrap', [], $betaCookie)
        ->assertOk()
        ->assertJsonPath('data.remote_modules', []);
});

it('never filters remote_modules by platform compatibility server-side (decision D8)', function () {
    $acme = bootDemoTenant('acme', withDemo: true);
    tenantUser($acme, ['email' => 'user@acme.test']);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    // The loader owns platform-compatibility refusal — the server always passes the
    // remote through verbatim, even when platform_version has moved on to a major the
    // addon's declared "^1.0" constraint no longer satisfies.
    config(['zenon.platform_version' => '2.0.0']);

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/bootstrap', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.remote_modules', [[
            'id' => 'demo',
            'url' => '/modules/thirdparty/Demo/dist/remoteEntry.js',
            'platform' => '^1.0',
        ]])
        ->assertJsonPath('data.platform_version', '2.0.0');
});

it('keeps demo behaviorally invisible: it registers no routes even where enabled', function () {
    $acme = bootDemoTenant('acme', withDemo: true);

    // Demo ships zero routes (module.json emits no routes/api.php) — the hook/listener
    // gates covered by the scenarios above ARE the invisibility surface; this only
    // confirms a never-registered route stays a plain 404, not something else.
    assertModuleInvisibleFor($acme, '/api/v1/demo/anything');
});
