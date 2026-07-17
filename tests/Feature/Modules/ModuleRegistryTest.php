<?php

use App\Foundation\Modules\Models\InstalledModule;
use App\Foundation\Modules\Models\TenantModule;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Support\Facades\DB;

it('treats installed as central rows intersected with on-disk manifests', function () {
    installModule('dummy');

    InstalledModule::query()->create([
        'alias' => 'ghost', 'name' => 'Ghost', 'version' => '1.0.0', 'core' => false, 'installed_at' => now(),
    ]);

    $registry = app(ModuleRegistry::class);
    $registry->flush();

    expect($registry->installed())->toHaveKey('dummy')
        ->not->toHaveKey('ghost');
});

it('answers isEnabledForCurrentTenant per context', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    $beta = createTenant('beta');
    enableModule('dummy', $acme);

    $registry = app(ModuleRegistry::class);

    expect($registry->isEnabledForCurrentTenant('dummy'))->toBeFalse(); // central context

    tenancy()->initialize($acme);
    expect($registry->isEnabledForCurrentTenant('dummy'))->toBeTrue();
    tenancy()->end();

    tenancy()->initialize($beta);
    expect($registry->isEnabledForCurrentTenant('dummy'))->toBeFalse();
    tenancy()->end();
});

it('memoizes enablement per request and refreshes after a flush', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    $registry = app(ModuleRegistry::class);
    $central = DB::connection((string) config('tenancy.database.central_connection'));

    $registry->enabledFor('acme'); // warm the memo

    $central->enableQueryLog();
    $registry->enabledFor('acme');
    expect($central->getQueryLog())->toBeEmpty(); // memo hit — no query
    $central->disableQueryLog();

    // Direct DB flip is invisible until the cache is flushed…
    TenantModule::query()->where('tenant_id', 'acme')->update(['enabled' => false]);
    expect($registry->isEnabledFor('dummy', 'acme'))->toBeTrue();

    // …and visible after.
    $registry->flushTenantCache('acme');
    expect($registry->isEnabledFor('dummy', 'acme'))->toBeFalse();
});
