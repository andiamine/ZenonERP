<?php

use App\Foundation\Modules\Events\ModuleUpgraded;
use App\Foundation\Modules\Events\ModuleUpgradedForTenant;
use App\Foundation\Modules\Models\InstalledModule;
use App\Foundation\Modules\Models\TenantModule;
use App\Foundation\Modules\ModuleManager;
use App\Foundation\Modules\ModuleRegistry;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

/**
 * Rewinds a tenant to "version 0.9.0" of dummy: removes the second migration from the
 * tenant ledger + drops its table, and marks central metadata accordingly.
 */
function simulateOldDummy(Tenant $tenant): void
{
    $tenant->run(function (): void {
        DB::table('migrations')->where('migration', '2026_07_17_000200_create_dummy_item_notes_table')->delete();
        Schema::dropIfExists('dummy_item_notes');
    });

    TenantModule::query()
        ->where('tenant_id', (string) $tenant->getTenantKey())
        ->where('module', 'dummy')
        ->update(['migrated_version' => '0.9.0']);

    InstalledModule::query()->where('alias', 'dummy')->update(['version' => '0.9.0']);

    app(ModuleRegistry::class)->flush();
}

it('fans out to enabled tenants only and converges them (§12 acceptance)', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    $beta = createTenant('beta');
    $gamma = createTenant('gamma');

    enableModule('dummy', $acme);
    enableModule('dummy', $beta);
    enableModule('dummy', $gamma);
    app(ModuleManager::class)->disableForTenant('dummy', $gamma);

    simulateOldDummy($acme);
    simulateOldDummy($beta);
    simulateOldDummy($gamma);

    Event::fake([ModuleUpgraded::class, ModuleUpgradedForTenant::class]);

    app(ModuleManager::class)->upgrade('dummy');

    expect($acme->run(fn () => Schema::hasTable('dummy_item_notes')))->toBeTrue()
        ->and($beta->run(fn () => Schema::hasTable('dummy_item_notes')))->toBeTrue()
        ->and($gamma->run(fn () => Schema::hasTable('dummy_item_notes')))->toBeFalse(); // disabled → skipped

    expect(TenantModule::query()->where('module', 'dummy')->where('tenant_id', 'acme')->value('migrated_version'))->toBe('1.0.0')
        ->and(TenantModule::query()->where('module', 'dummy')->where('tenant_id', 'gamma')->value('migrated_version'))->toBe('0.9.0');

    expect(InstalledModule::query()->where('alias', 'dummy')->value('version'))->toBe('1.0.0');

    Event::assertDispatched(ModuleUpgraded::class, fn ($e) => $e->fromVersion === '0.9.0' && $e->toVersion === '1.0.0');
    Event::assertDispatched(ModuleUpgradedForTenant::class, 2);
    Event::assertNotDispatched(ModuleUpgradedForTenant::class, fn ($e) => $e->tenantId === 'gamma');
});

it('isolates a failing tenant and converges it on retry (risk #2)', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    $beta = createTenant('beta');

    enableModule('dummy', $acme);
    enableModule('dummy', $beta);

    simulateOldDummy($acme);
    simulateOldDummy($beta);

    $acme->run(fn () => Schema::create('dummy_poison', fn ($t) => $t->id())); // poison acme's v2 migration

    app(ModuleManager::class)->upgrade('dummy'); // must not throw

    expect($beta->run(fn () => Schema::hasTable('dummy_item_notes')))->toBeTrue()
        ->and(TenantModule::query()->where('module', 'dummy')->where('tenant_id', 'beta')->value('migrated_version'))->toBe('1.0.0');

    expect($acme->run(fn () => Schema::hasTable('dummy_item_notes')))->toBeFalse()
        ->and(TenantModule::query()->where('module', 'dummy')->where('tenant_id', 'acme')->value('migrated_version'))->toBe('0.9.0');

    // Retry after removing the poison: the tenant ledger makes re-runs idempotent.
    $acme->run(fn () => Schema::drop('dummy_poison'));

    app(ModuleManager::class)->upgrade('dummy');

    expect($acme->run(fn () => Schema::hasTable('dummy_item_notes')))->toBeTrue()
        ->and(TenantModule::query()->where('module', 'dummy')->where('tenant_id', 'acme')->value('migrated_version'))->toBe('1.0.0');
});

it('reports the fan-out via zenon:module:upgrade', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);
    simulateOldDummy($acme);

    $this->artisan('zenon:module:upgrade', ['alias' => 'dummy'])->assertSuccessful();

    expect(TenantModule::query()->where('module', 'dummy')->where('tenant_id', 'acme')->value('migrated_version'))->toBe('1.0.0');
});
