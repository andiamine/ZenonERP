<?php

use App\Foundation\Modules\Models\TenantModule;
use Illuminate\Support\Facades\Schema;

it('auto-enables installed core modules for newly created tenants', function () {
    installModule('dummycore');

    $acme = createTenant('acme');

    expect($acme->run(fn () => Schema::hasTable('dummy_core_settings')))->toBeTrue()
        ->and($acme->run(fn () => Schema::hasTable('users')))->toBeTrue(); // platform base migrations still ran
    expect(TenantModule::query()->where('tenant_id', 'acme')->where('module', 'dummycore')->where('enabled', true)->exists())
        ->toBeTrue();
});

it('enables zenon.default_modules (with dependencies) via the identical flow', function () {
    installModule('dummydep');
    config(['zenon.default_modules' => ['dummydep']]);

    $acme = createTenant('acme');

    expect($acme->run(fn () => Schema::hasTable('dummy_items')))->toBeTrue()      // dependency auto-enabled
        ->and($acme->run(fn () => Schema::hasTable('dummydep_things')))->toBeTrue();

    expect(TenantModule::query()->where('tenant_id', 'acme')->where('enabled', true)->pluck('module')->all())
        ->toContain('dummy', 'dummydep');
});

it('is a graceful no-op when nothing is installed', function () {
    $acme = createTenant('acme');

    expect(TenantModule::query()->where('tenant_id', 'acme')->count())->toBe(0)
        ->and($acme->run(fn () => Schema::hasTable('users')))->toBeTrue();
});
