<?php

use App\Foundation\Modules\Events\ModuleEnabledForTenant;
use App\Foundation\Modules\Exceptions\ModuleNotInstalledException;
use App\Foundation\Modules\Models\TenantModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Dummy\DummyModule;

beforeEach(function () {
    DummyModule::$log = [];
});

it('creates module tables only in the enabled tenant (§12 acceptance)', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    $beta = createTenant('beta');

    enableModule('dummy', $acme);

    expect($acme->run(fn () => Schema::hasTable('dummy_items')))->toBeTrue()
        ->and($beta->run(fn () => Schema::hasTable('dummy_items')))->toBeFalse();

    $ledger = $acme->run(fn () => DB::table('migrations')->pluck('migration')->all());
    expect($ledger)->toContain('2026_07_17_000100_create_dummy_items_table')
        ->toContain('2026_07_17_000200_create_dummy_item_notes_table');

    $row = TenantModule::query()->where('tenant_id', 'acme')->where('module', 'dummy')->first();
    expect($row)->not->toBeNull()
        ->and($row->enabled)->toBeTrue()
        ->and($row->migrated_version)->toBe('1.0.0');

    expect(TenantModule::query()->where('tenant_id', 'beta')->exists())->toBeFalse();
});

it('serves module routes for the enabled tenant only (§12 acceptance)', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    $beta = createTenant('beta');

    enableModule('dummy', $acme);

    $this->getJson('http://acme.zenonerp.test/api/v1/dummy/items')
        ->assertOk()
        ->assertJsonPath('data.0.label', 'seed');

    assertModuleInvisibleFor($beta, '/api/v1/dummy/items');
});

it('is idempotent: enabling twice runs seeds and migrations once', function () {
    installModule('dummy');
    $acme = createTenant('acme');

    enableModule('dummy', $acme);
    enableModule('dummy', $acme);

    expect($acme->run(fn () => DB::table('dummy_items')->count()))->toBe(1)
        ->and(TenantModule::query()->where('tenant_id', 'acme')->where('module', 'dummy')->count())->toBe(1);
});

it('auto-enables dependencies in topological order', function () {
    installModule('dummydep');
    $acme = createTenant('acme');

    enableModule('dummydep', $acme);

    expect($acme->run(fn () => Schema::hasTable('dummy_items')))->toBeTrue()
        ->and($acme->run(fn () => Schema::hasTable('dummydep_things')))->toBeTrue();

    expect(TenantModule::query()->where('tenant_id', 'acme')->where('enabled', true)->pluck('module')->all())
        ->toContain('dummy', 'dummydep');
});

it('runs lifecycle hooks in order: enabling (central) then enabled (tenant)', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    DummyModule::$log = [];

    enableModule('dummy', $acme);

    expect(DummyModule::$log)->toBe(['enabling:acme', 'enabled:acme']);
});

it('fires ModuleEnabledForTenant and rejects non-installed modules', function () {
    installModule('dummy');
    $acme = createTenant('acme');

    Event::fake([ModuleEnabledForTenant::class]);
    enableModule('dummy', $acme);
    Event::assertDispatched(ModuleEnabledForTenant::class, fn ($e) => $e->alias === 'dummy' && $e->tenantId === 'acme');

    expect(fn () => enableModule('dummycore', $acme))->toThrow(ModuleNotInstalledException::class);
});

it('exposes enable via zenon:module:enable', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    $beta = createTenant('beta');

    $this->artisan('zenon:module:enable', ['alias' => 'dummy', '--tenant' => 'acme'])->assertSuccessful();
    expect($acme->run(fn () => Schema::hasTable('dummy_items')))->toBeTrue();

    $this->artisan('zenon:module:enable', ['alias' => 'dummy', '--all-tenants' => true])->assertSuccessful();
    expect($beta->run(fn () => Schema::hasTable('dummy_items')))->toBeTrue();

    $this->artisan('zenon:module:enable', ['alias' => 'dummy'])->assertFailed(); // neither option
});
