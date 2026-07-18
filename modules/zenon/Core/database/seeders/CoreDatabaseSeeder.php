<?php

namespace Modules\Core\Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Company;
use Modules\Core\Models\Currency;
use Spatie\Permission\Models\Role;

/**
 * Idempotent by contract — ModuleManager::enableOne()/upgradeForTenant() re-run this on
 * every enable AND upgrade. Every step below is safe to run twice.
 */
class CoreDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCurrencies();

        $company = $this->seedDefaultCompany();

        $this->attachOrphanUsersToDefaultCompany($company);

        $this->seedAdminRole();
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
            ['code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => '£', 'decimal_places' => 2],
            ['code' => 'MAD', 'name' => 'Moroccan Dirham', 'symbol' => 'DH', 'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(['code' => $currency['code']], $currency);
        }
    }

    private function seedDefaultCompany(): Company
    {
        $existingDefault = Company::query()->where('is_default', true)->first();

        if ($existingDefault !== null) {
            return $existingDefault;
        }

        $tenant = tenant();
        $tenantName = $tenant instanceof Tenant ? $tenant->name : null;

        // firstOrCreate on `code` (not a blind create) guards the collision case: a
        // 'MAIN'-coded company already existing without is_default set (companies.code
        // carries its own unique index).
        return Company::query()->firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => $tenantName ?? 'Main Company',
                'currency_code' => 'USD',
                'is_default' => true,
            ],
        );
    }

    private function attachOrphanUsersToDefaultCompany(Company $company): void
    {
        /** @var list<int> $assignedUserIds */
        $assignedUserIds = DB::table('company_user')->pluck('user_id')->all();

        /** @var list<int> $orphanUserIds */
        $orphanUserIds = User::query()->whereNotIn('id', $assignedUserIds)->pluck('id')->all();

        if ($orphanUserIds === []) {
            return;
        }

        $company->users()->syncWithoutDetaching($orphanUserIds);
    }

    /**
     * NO permission grants on the admin role — CoreServiceProvider's Gate::before treats
     * hasRole('admin') as an unconditional super-user bypass (CLAUDE.md §9.1).
     */
    private function seedAdminRole(): void
    {
        Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        if (User::query()->role('admin')->exists()) {
            return;
        }

        // No admin yet: make the lowest-id user (the signup-created tenant owner, in
        // practice) the admin so the tenant isn't locked out of its own permission UI.
        User::query()->orderBy('id')->first()?->assignRole('admin');
    }
}
