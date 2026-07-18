<?php

use App\Foundation\Modules\Events\ModuleEnabledForTenant;
use App\Foundation\Modules\Models\TenantModule;
use Illuminate\Support\Facades\Event;
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

/**
 * §12's Phase 5 verify criterion: with core + sequence + audit installed and both
 * non-core modules configured as defaults, a fresh tenant is provisioned in
 * dependency-respecting topological order — core (the shared dependency) before its
 * dependents, sequence before audit only because it's listed first in default_modules
 * (both independently depend on core only, so their relative order isn't itself an
 * invariant, but core-first is).
 */
it('provisions core, sequence, and audit in topological order for a fresh tenant', function () {
    installModule('core');
    installModule('sequence');
    installModule('audit');
    config(['zenon.default_modules' => ['sequence', 'audit']]);

    Event::fake([ModuleEnabledForTenant::class]);

    $acme = createTenant('acme');

    Event::assertDispatched(ModuleEnabledForTenant::class, 3);

    $dispatchedOrder = collect(Event::dispatched(ModuleEnabledForTenant::class))
        ->map(fn (array $args) => $args[0]->alias)
        ->values()
        ->all();

    expect($dispatchedOrder)->toBe(['core', 'sequence', 'audit']);

    expect(TenantModule::query()->where('tenant_id', 'acme')->where('enabled', true)->pluck('module')->sort()->values()->all())
        ->toBe(['audit', 'core', 'sequence']);
});
