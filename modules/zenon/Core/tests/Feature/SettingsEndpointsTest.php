<?php

use Modules\Core\Models\Company;
use Modules\Core\Models\Setting;

it('returns the effective settings map with registered defaults', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.settings.view'));

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data', [
            'core.default_currency' => 'USD',
            'core.date_format' => 'Y-m-d',
            'core.timezone' => 'UTC',
            'core.fiscal_year_start_month' => 1,
        ]);
});

it('lists the four core setting definitions with key/type/default/label', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.settings.view'));

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings/definitions', [], $cookie)
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data', [
            ['key' => 'core.default_currency', 'type' => 'string', 'default' => 'USD', 'label' => null],
            ['key' => 'core.date_format', 'type' => 'string', 'default' => 'Y-m-d', 'label' => null],
            ['key' => 'core.timezone', 'type' => 'string', 'default' => 'UTC', 'label' => null],
            ['key' => 'core.fiscal_year_start_month', 'type' => 'int', 'default' => 1, 'label' => null],
        ]);
});

/**
 * Controller-directed fix (audit module task, follow-up): CLAUDE.md §6/§13 risk #1 — a
 * disabled module's setting must be behaviorally invisible, not merely absent from the
 * SPA menu. Definitions register platform-wide (every installed module's provider boots
 * regardless of tenant enablement — same as routes always registering, §5), but
 * SettingDefinition::$module gates the READ/WRITE path: SettingsRepository::get()/all()/
 * set() and SettingsController::definitions() all consult
 * ModuleRegistry::isEnabledForCurrentTenant($definition->module).
 */
it('hides a gated setting owned by a disabled module and reveals it once that module is enabled', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo(['core.settings.view', 'core.settings.update']));

    installModule('audit'); // installed platform-wide, NOT enabled for this tenant yet

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    // audit disabled → invisible on BOTH endpoints, and a write is rejected as unknown
    // (invisible means invisible — the caller cannot distinguish "never registered" from
    // "registered by a module disabled for this tenant").
    $settings = statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings', [], $cookie)
        ->assertOk()->json('data');
    expect(array_key_exists('audit.retention_days', $settings))->toBeFalse();

    $definitions = collect(
        statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings/definitions', [], $cookie)
            ->assertOk()->json('data'),
    );
    expect($definitions->firstWhere('key', 'audit.retention_days'))->toBeNull();

    $rejected = statefulJson('put', 'acme.zenonerp.test', '/api/v1/core/settings', [
        'values' => ['audit.retention_days' => 30],
    ], $cookie);
    assertErrorEnvelope($rejected, 422, 'validation_error');
    expect(array_key_exists('values.audit.retention_days', $rejected->json('error.errors')))->toBeTrue();

    // Enable audit → the setting appears with its registered default, and is now writable.
    enableModule('audit', $tenant);

    $settings = statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings', [], $cookie)
        ->assertOk()->json('data');
    expect($settings['audit.retention_days'])->toBe(365);

    $definitions = collect(
        statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings/definitions', [], $cookie)
            ->assertOk()->json('data'),
    );
    expect($definitions->firstWhere('key', 'audit.retention_days'))->toBe([
        'key' => 'audit.retention_days', 'type' => 'int', 'default' => 365,
        'label' => 'Audit log retention (days)',
    ]);

    statefulJson('put', 'acme.zenonerp.test', '/api/v1/core/settings', [
        'values' => ['audit.retention_days' => 30],
    ], $cookie)
        ->assertOk()
        ->tap(fn ($response) => expect($response->json('data')['audit.retention_days'])->toBe(30));
});

/**
 * §12 VERIFY CRITERION: X-Company-Id scopes settings. Writing with a company header
 * scopes the override to that company only; other companies (and the no-header default
 * company fallback) keep seeing the tenant/registered value.
 */
it('scopes a settings write to the company named by X-Company-Id (§12 verify criterion)', function () {
    $tenant = bootCoreTenant();
    $admin = tenantUser($tenant, ['email' => 'admin@acme.test']);
    $tenant->run(function () use ($admin) {
        $admin->assignRole('admin');
        Company::query()->where('is_default', true)->firstOrFail()->users()->attach($admin);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    $companyAId = $tenant->run(fn () => Company::query()->where('is_default', true)->firstOrFail()->id);

    $companyBId = statefulJson('post', 'acme.zenonerp.test', '/api/v1/core/companies', [
        'name' => 'Beta Co', 'code' => 'BETA', 'currency_code' => 'USD',
    ], $cookie)->assertCreated()->json('data.id');

    statefulJson('put', 'acme.zenonerp.test', "/api/v1/core/companies/{$companyBId}/users", ['user_ids' => [$admin->id]], $cookie)
        ->assertOk();

    // NOTE: settings keys (e.g. "core.default_currency") contain literal dots and are
    // flat map keys, not nested objects — assertJsonPath's dot-notation would misparse
    // them as a nested path, so every assertion below reads the whole `data` map back
    // via ->json('data') and indexes it with plain PHP array access instead.
    statefulJson('put', 'acme.zenonerp.test', '/api/v1/core/settings', ['values' => ['core.default_currency' => 'EUR']], $cookie, [
        'X-Company-Id' => (string) $companyBId,
    ])
        ->assertOk()
        ->tap(fn ($response) => expect($response->json('data')['core.default_currency'])->toBe('EUR'));

    // Company B now sees EUR.
    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings', [], $cookie, ['X-Company-Id' => (string) $companyBId])
        ->assertOk()
        ->tap(fn ($response) => expect($response->json('data')['core.default_currency'])->toBe('EUR'));

    // Company A (default) is untouched — still the registered default USD.
    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings', [], $cookie, ['X-Company-Id' => (string) $companyAId])
        ->assertOk()
        ->tap(fn ($response) => expect($response->json('data')['core.default_currency'])->toBe('USD'));

    // No header at all → SetCurrentCompany falls back to the user's default company (A) → still USD.
    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings', [], $cookie)
        ->assertOk()
        ->tap(fn ($response) => expect($response->json('data')['core.default_currency'])->toBe('USD'));
});

/**
 * PutSettings always writes at CurrentCompany::id(); SetCurrentCompany only leaves that
 * null for a user with NO company assignment at all (defaultCompanyIdFor returns null).
 * That is the one path that produces a true tenant-level (company_id NULL) row.
 */
it('writes a tenant-level setting (company_id NULL) only when the acting user has no company at all', function () {
    $tenant = bootCoreTenant();
    $orphan = tenantUser($tenant, ['email' => 'orphan@acme.test']); // created after the seeder ran — zero companies
    $admin = tenantUser($tenant, ['email' => 'admin@acme.test']);   // attached to the default company, for the read-back
    $tenant->run(function () use ($orphan, $admin) {
        $orphan->givePermissionTo(['core.settings.view', 'core.settings.update']);
        $admin->assignRole('admin');
        Company::query()->where('is_default', true)->firstOrFail()->users()->attach($admin);
    });

    [, $orphanCookie] = loginOn('acme.zenonerp.test', 'orphan@acme.test');
    [, $adminCookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    statefulJson('put', 'acme.zenonerp.test', '/api/v1/core/settings', ['values' => ['core.timezone' => 'Africa/Casablanca']], $orphanCookie)
        ->assertOk()
        ->tap(fn ($response) => expect($response->json('data')['core.timezone'])->toBe('Africa/Casablanca'));

    $tenant->run(function () {
        expect(Setting::query()->whereNull('company_id')->where('key', 'core.timezone')->exists())->toBeTrue();
    });

    // A company-scoped read (admin, attached to the default company) now sees the same
    // value too — the tenant-level row is the base layer of the merge, nothing shadows it.
    $companyAId = $tenant->run(fn () => Company::query()->where('is_default', true)->firstOrFail()->id);
    statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings', [], $adminCookie, ['X-Company-Id' => (string) $companyAId])
        ->assertOk()
        ->tap(fn ($response) => expect($response->json('data')['core.timezone'])->toBe('Africa/Casablanca'));

    // The orphan themselves cannot even address a company header — they belong to none.
    assertErrorEnvelope(
        statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings', [], $orphanCookie, ['X-Company-Id' => (string) $companyAId]),
        403,
        'forbidden',
    );
});

it('rejects an unregistered setting key with a 422 attributed to values.<key>', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.settings.update'));

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    $response = statefulJson('put', 'acme.zenonerp.test', '/api/v1/core/settings', [
        'values' => ['core.nonexistent_setting' => 'x'],
    ], $cookie);

    assertErrorEnvelope($response, 422, 'validation_error');
    expect(array_key_exists('values.core.nonexistent_setting', $response->json('error.errors')))->toBeTrue();
});

it('rejects a value of the wrong type with a 422 attributed to values.<key>', function () {
    $tenant = bootCoreTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('core.settings.update'));

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    // core.default_currency is a 'string' setting — sending an int must fail type validation.
    $response = statefulJson('put', 'acme.zenonerp.test', '/api/v1/core/settings', [
        'values' => ['core.default_currency' => 123],
    ], $cookie);

    assertErrorEnvelope($response, 422, 'validation_error');
    expect(array_key_exists('values.core.default_currency', $response->json('error.errors')))->toBeTrue();
});

it('rejects a foreign X-Company-Id (a company the user is not assigned to) with the forbidden envelope', function () {
    $tenant = bootCoreTenant();
    $admin = tenantUser($tenant, ['email' => 'admin@acme.test']);

    // Company B is created directly, NOT via POST /companies — that endpoint now auto-attaches
    // its creator (a member could never be "foreign"), so a genuinely foreign company has to
    // be seeded without the admin as a member.
    $companyBId = $tenant->run(function () use ($admin) {
        $admin->assignRole('admin');

        return Company::factory()->create(['code' => 'BETA', 'is_default' => false])->id;
    });

    [, $adminCookie] = loginOn('acme.zenonerp.test', 'admin@acme.test');

    assertErrorEnvelope(
        statefulJson('get', 'acme.zenonerp.test', '/api/v1/core/settings', [], $adminCookie, ['X-Company-Id' => (string) $companyBId]),
        403,
        'forbidden',
    );
});
