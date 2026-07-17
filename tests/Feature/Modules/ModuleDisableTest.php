<?php

use App\Foundation\Modules\Events\ModuleDisabledForTenant;
use App\Foundation\Modules\Exceptions\DependencyException;
use App\Foundation\Modules\Exceptions\ModuleStateException;
use App\Foundation\Modules\Models\TenantModule;
use App\Foundation\Modules\ModuleManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Dummy\DummyModule;

beforeEach(function () {
    DummyModule::$log = [];
});

it('gates routes off immediately while keeping data intact (§12 acceptance)', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    $acme->run(fn () => DB::table('dummy_items')->insert([
        'label' => 'user-data', 'created_at' => now(), 'updated_at' => now(),
    ]));

    $this->getJson('http://acme.zenonerp.test/api/v1/dummy/items')->assertOk();

    Event::fake([ModuleDisabledForTenant::class]);
    app(ModuleManager::class)->disableForTenant('dummy', $acme);

    // Same-process 404 proves the registry memo was flushed, not just stale-read.
    assertModuleInvisibleFor($acme, '/api/v1/dummy/items');

    expect($acme->run(fn () => DB::table('dummy_items')->count()))->toBe(2); // seed + user row intact

    $row = TenantModule::query()->where('tenant_id', 'acme')->where('module', 'dummy')->first();
    expect($row->enabled)->toBeFalse()
        ->and($row->migrated_version)->toBe('1.0.0'); // kept

    expect(DummyModule::$log)->toContain('disabled:acme');
    Event::assertDispatched(ModuleDisabledForTenant::class);
});

it('refuses to disable a module with enabled dependents', function () {
    installModule('dummydep');
    $acme = createTenant('acme');
    enableModule('dummydep', $acme);

    expect(fn () => app(ModuleManager::class)->disableForTenant('dummy', $acme))
        ->toThrow(DependencyException::class, 'dummydep');

    $this->artisan('zenon:module:disable', ['alias' => 'dummy', '--tenant' => 'acme'])->assertFailed();
});

it('cascades dependents-first with --cascade', function () {
    installModule('dummydep');
    $acme = createTenant('acme');
    enableModule('dummydep', $acme);

    app(ModuleManager::class)->disableForTenant('dummy', $acme, cascade: true);

    expect(TenantModule::query()->where('tenant_id', 'acme')->where('enabled', true)->count())->toBe(0)
        ->and(TenantModule::query()->where('tenant_id', 'acme')->count())->toBe(2); // rows kept, disabled
});

it('refuses to disable a core module', function () {
    installModule('dummycore');
    $acme = createTenant('acme');

    expect(fn () => app(ModuleManager::class)->disableForTenant('dummycore', $acme))
        ->toThrow(ModuleStateException::class, 'core');
});
