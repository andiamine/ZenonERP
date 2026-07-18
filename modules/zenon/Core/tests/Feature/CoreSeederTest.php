<?php

use App\Foundation\Modules\ModuleManager;
use App\Models\User;
use Modules\Core\Models\Company;
use Modules\Core\Models\Currency;
use Spatie\Permission\Models\Role;

it('is idempotent across a re-run: still 4 currencies, 1 default company, 1 admin role, lowest-id user stays admin', function () {
    $tenant = createTenant('acme');
    installModule('core');

    // Both created BEFORE enable — so CoreDatabaseSeeder's seedAdminRole() has candidates
    // to pick from on first run (no admin exists yet); userA has the lower id.
    $userA = tenantUser($tenant, ['email' => 'a@acme.test']);
    $userB = tenantUser($tenant, ['email' => 'b@acme.test']);

    enableModule('core', $tenant);

    $tenant->run(function () use ($userA) {
        expect(Currency::query()->count())->toBe(4)
            ->and(Company::query()->count())->toBe(1)
            ->and(Company::query()->where('is_default', true)->count())->toBe(1)
            ->and(Role::query()->where('name', 'admin')->count())->toBe(1)
            ->and(User::query()->role('admin')->pluck('id')->all())->toBe([$userA->id]);
    });

    // Re-run the seeder via the exact same tenant-context flow an upgrade uses.
    app(ModuleManager::class)->upgradeForTenant('core', $tenant);

    $tenant->run(function () use ($userA) {
        expect(Currency::query()->count())->toBe(4)
            ->and(Company::query()->count())->toBe(1)
            ->and(Company::query()->where('is_default', true)->count())->toBe(1)
            ->and(Role::query()->where('name', 'admin')->count())->toBe(1)
            ->and(User::query()->role('admin')->pluck('id')->all())->toBe([$userA->id]); // still lowest-id, not re-picked
    });
});
