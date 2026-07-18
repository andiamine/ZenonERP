<?php

use App\Foundation\Modules\ModuleManager;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/*
 * CLAUDE.md §9.1: modules declare permissions in their manifest; ModuleManager syncs
 * them into the tenant DB on enable/upgrade via PermissionSynchronizer. Dummy's fixture
 * manifest carries "dummy.items.view" specifically to exercise this end-to-end.
 */

it('creates the manifest permissions in the enabled tenant DB only (§9.1)', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    $beta = createTenant('beta');

    enableModule('dummy', $acme);

    $acme->run(function () {
        expect(Permission::query()->where('name', 'dummy.items.view')->where('guard_name', 'web')->exists())->toBeTrue();
    });

    $beta->run(function () {
        expect(Permission::query()->where('name', 'dummy.items.view')->exists())->toBeFalse();
    });
});

it('is idempotent: re-syncing on upgrade does not duplicate the permission row', function () {
    installModule('dummy');
    $acme = createTenant('acme');

    enableModule('dummy', $acme);

    // upgradeForTenant always re-runs the tenant-context flow (migrate → sync → seed),
    // even with no version change — the direct way to re-exercise the synchronizer
    // against an already-enabled tenant (re-enabling itself is a no-op, see ModuleEnableTest).
    app(ModuleManager::class)->upgradeForTenant('dummy', $acme);

    $acme->run(function () {
        expect(Permission::query()->where('name', 'dummy.items.view')->count())->toBe(1);
    });
});

it('preserves an existing role grant across an upgrade re-sync (create-only policy)', function () {
    installModule('dummy');
    $acme = createTenant('acme');

    enableModule('dummy', $acme);

    $acme->run(function () {
        Role::query()->create(['name' => 'dummy-manager', 'guard_name' => 'web'])
            ->givePermissionTo('dummy.items.view');
    });

    app(ModuleManager::class)->upgradeForTenant('dummy', $acme);

    $acme->run(function () {
        $role = Role::query()->where('name', 'dummy-manager')->firstOrFail();

        expect($role->hasPermissionTo('dummy.items.view'))->toBeTrue();
    });
});
