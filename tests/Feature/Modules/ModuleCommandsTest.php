<?php

use App\Foundation\Modules\Models\InstalledModule;
use App\Foundation\Modules\Models\TenantModule;
use App\Foundation\Modules\ModuleManager;

it('lists modules with install state and enablement counts', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    $this->artisan('zenon:module:list')
        ->expectsOutputToContain('dummy')
        ->assertSuccessful();
});

it('doctor is green on a clean state', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    $this->artisan('zenon:module:doctor')->assertSuccessful();
});

it('doctor flags migrated_version drift', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    TenantModule::query()->where('tenant_id', 'acme')->update(['migrated_version' => '0.5.0']);

    $this->artisan('zenon:module:doctor')
        ->expectsOutputToContain('acme')
        ->assertFailed();
});

it('doctor flags statuses-file desync', function () {
    installModule('dummy');

    // Simulate desync: the modules row references a name the activator has no status for.
    InstalledModule::query()->where('alias', 'dummy')->update(['name' => 'Nope']);

    $this->artisan('zenon:module:doctor')->assertFailed();
});

it('uninstall refuses with enabled tenants, then cleans up fully', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    $this->artisan('zenon:module:uninstall', ['alias' => 'dummy'])->assertFailed();

    app(ModuleManager::class)->disableForTenant('dummy', $acme);

    $this->artisan('zenon:module:uninstall', ['alias' => 'dummy'])->assertSuccessful();

    expect(InstalledModule::query()->where('alias', 'dummy')->exists())->toBeFalse()
        ->and(TenantModule::query()->where('module', 'dummy')->exists())->toBeFalse(); // FK cascade

    $statuses = json_decode((string) file_get_contents(base_path((string) env('MODULES_STATUSES_FILE'))), true);
    expect($statuses['Dummy'] ?? null)->toBeFalse();
});
