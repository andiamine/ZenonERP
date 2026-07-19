<?php

use App\Foundation\Hooks\HookBus;
use Illuminate\Support\Facades\Event;
use Modules\Core\Contracts\Events\CompanyDeleted;
use Modules\Core\Contracts\Hooks\CompanyApiResponse;
use Modules\Core\Contracts\Hooks\CompanyDeleting;
use Modules\Core\Models\Company;
use Tests\Fixtures\Hooks\AddCompanyIdMarker;
use Tests\Fixtures\Hooks\VetoCompanyDelete;

/*
 * Phase 7 Task 1: Core's first hook emission points (CLAUDE.md §6/§9.2). Reuses
 * bootCoreTenant() from CompanyEndpointsTest.php (loaded into the same Pest run).
 */

it('serializes extra as an empty object on index and show when no filters are registered', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.companies.view'));
    $companyId = $tenant->run(fn () => Company::query()->where('is_default', true)->firstOrFail()->id);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    $index = statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/companies', [], $cookie)->assertOk();
    expect($index->getContent())->toContain('"extra":{}');

    $show = statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/companies/{$companyId}", [], $cookie)->assertOk();
    expect($show->getContent())->toContain('"extra":{}');
});

it('deletes a non-default, non-last company and fires CompanyDeleted when no filters are registered', function () {
    Event::fake([CompanyDeleted::class]);

    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.companies.delete'));
    $companyId = $tenant->run(fn () => Company::factory()->create(['code' => 'SIDE'])->id);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/companies/{$companyId}", [], $cookie)
        ->assertNoContent();

    $tenant->run(fn () => expect(Company::find($companyId))->toBeNull());

    Event::assertDispatched(
        CompanyDeleted::class,
        fn (CompanyDeleted $event): bool => $event->companyId === $companyId && $event->code === 'SIDE',
    );
});

it('appends filter-computed extra fields keyed by company id on both index and show', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.companies.view'));
    $companyId = $tenant->run(fn () => Company::query()->where('is_default', true)->firstOrFail()->id);

    app(HookBus::class)->register(CompanyApiResponse::class, AddCompanyIdMarker::class, 'core');

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/companies', [], $cookie)
        ->assertOk()
        ->assertJsonPath("extra.{$companyId}.marked", true);

    statefulJson('get', 'acme.zenonerp.test', "/api/v1/core/companies/{$companyId}", [], $cookie)
        ->assertOk()
        ->assertJsonPath("extra.{$companyId}.marked", true);
});

it('returns the 422 action_vetoed envelope when a filter vetoes the delete, and never dispatches CompanyDeleted', function () {
    Event::fake([CompanyDeleted::class]);

    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.companies.delete'));
    $companyId = $tenant->run(fn () => Company::factory()->create(['code' => 'SIDE'])->id);

    app(HookBus::class)->register(CompanyDeleting::class, VetoCompanyDelete::class, 'core');

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    $response = statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/companies/{$companyId}", [], $cookie);

    assertErrorEnvelope($response, 422, 'action_vetoed')
        ->assertJsonPath('error.code', 'test.veto_code');

    $tenant->run(fn () => expect(Company::find($companyId))->not->toBeNull());

    Event::assertNotDispatched(CompanyDeleted::class);
});

it('still blocks deleting the default company with a 409 envelope (regression)', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.companies.delete'));
    $mainId = $tenant->run(fn () => Company::query()->where('is_default', true)->firstOrFail()->id);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    assertErrorEnvelope(
        statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/companies/{$mainId}", [], $cookie),
        409,
        'conflict',
    );
});

it('still blocks deleting the last remaining company with a 409 envelope (regression)', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.companies.delete'));

    $mainId = $tenant->run(function () {
        $main = Company::query()->where('is_default', true)->firstOrFail();
        $main->update(['is_default' => false]);

        return $main->id;
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    assertErrorEnvelope(
        statefulJson('delete', 'acme.zenonerp.test', "/api/v1/core/companies/{$mainId}", [], $cookie),
        409,
        'conflict',
    );
});
