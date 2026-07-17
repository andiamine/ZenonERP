<?php

use App\Foundation\Modules\Events\ModulePurgedForTenant;
use App\Foundation\Modules\Exceptions\ModuleStateException;
use App\Foundation\Modules\Models\TenantModule;
use App\Foundation\Modules\ModuleManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Dummy\DummyModule;

beforeEach(function () {
    DummyModule::$log = [];
});

it('refuses to purge while enabled', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    expect(fn () => app(ModuleManager::class)->purgeForTenant('dummy', $acme))
        ->toThrow(ModuleStateException::class, 'must be disabled');
});

it('purges a disabled module: drops tables, clears ledger, deletes the row', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    $beta = createTenant('beta');
    enableModule('dummy', $acme);
    enableModule('dummy', $beta);

    $manager = app(ModuleManager::class);
    $manager->disableForTenant('dummy', $acme);

    Event::fake([ModulePurgedForTenant::class]);
    $manager->purgeForTenant('dummy', $acme);

    expect($acme->run(fn () => Schema::hasTable('dummy_items')))->toBeFalse()
        ->and($acme->run(fn () => Schema::hasTable('dummy_item_notes')))->toBeFalse();

    $ledger = $acme->run(fn () => DB::table('migrations')->pluck('migration')->all());
    expect($ledger)->not->toContain('2026_07_17_000100_create_dummy_items_table');

    expect(TenantModule::query()->where('tenant_id', 'acme')->where('module', 'dummy')->exists())->toBeFalse();

    // The other tenant is untouched.
    expect($beta->run(fn () => Schema::hasTable('dummy_items')))->toBeTrue();

    expect(DummyModule::$log)->toContain('purging:acme');
    Event::assertDispatched(ModulePurgedForTenant::class);
});

it('confirms before purging via the command', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);
    app(ModuleManager::class)->disableForTenant('dummy', $acme);

    $this->artisan('zenon:module:purge', ['alias' => 'dummy', '--tenant' => 'acme'])
        ->expectsConfirmation("This DROPS all [dummy] tables in tenant [acme]'s database. Continue?", 'no')
        ->assertFailed();

    expect($acme->run(fn () => Schema::hasTable('dummy_items')))->toBeTrue();

    $this->artisan('zenon:module:purge', ['alias' => 'dummy', '--tenant' => 'acme', '--force' => true])
        ->assertSuccessful();

    expect($acme->run(fn () => Schema::hasTable('dummy_items')))->toBeFalse();
});
