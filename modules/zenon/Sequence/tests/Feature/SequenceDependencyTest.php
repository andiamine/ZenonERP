<?php

use App\Foundation\Modules\Events\ModuleEnabledForTenant;
use App\Foundation\Modules\Models\TenantModule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

/*
 * §12 acceptance: sequence depends on core via manifest `requires` and must resolve in
 * topological order — enabling sequence on a tenant where core is installed-but-not-enabled
 * auto-enables core FIRST (DependencyResolver), through the one shared enable code path.
 */
it('auto-enables core before sequence in topological order', function () {
    // Provisioned before anything is installed → the tenant starts with zero enabled modules.
    $tenant = createTenant('acme');
    installModule('sequence'); // resolver installs core + sequence platform-wide

    expect(TenantModule::query()->where('tenant_id', 'acme')->where('enabled', true)->exists())->toBeFalse();

    // Observe the REAL dispatch order (a live listener, not Event::fake, so order is real).
    $order = [];
    Event::listen(ModuleEnabledForTenant::class, function (ModuleEnabledForTenant $event) use (&$order) {
        $order[] = $event->alias;
    });

    enableModule('sequence', $tenant);

    expect($order)->toBe(['core', 'sequence']);

    $enabled = TenantModule::query()->where('tenant_id', 'acme')->where('enabled', true)->pluck('module')->all();
    expect($enabled)->toContain('core')->toContain('sequence');

    // Both modules' tenant schemas actually materialised in the tenant DB.
    expect($tenant->run(fn () => Schema::hasTable('companies')))->toBeTrue()
        ->and($tenant->run(fn () => Schema::hasTable('sequences')))->toBeTrue();
});
