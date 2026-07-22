<?php

namespace App\Foundation\Installer\Actions;

use App\Foundation\Modules\ModuleManager;
use App\Models\Tenant;
use App\Models\User;

/**
 * The installer's "admin" step (CLAUDE.md §7 Phase 8 Task 6). Deliberately ZERO
 * Foundation → Modules\Core imports: role assignment and MAIN-company membership are
 * NOT done here — they fall out of re-running Core's own idempotent seeder via
 * ModuleManager::upgradeForTenant('core', $tenant), which (per
 * Modules\Core\Database\Seeders\CoreDatabaseSeeder) assigns the `admin` role to the
 * lowest-id tenant user and attaches orphan users to the MAIN company. Resumable: user
 * creation is a no-op when the email already exists.
 */
final class CreateAdminUser
{
    public function __construct(private readonly ModuleManager $moduleManager) {}

    public function handle(Tenant $tenant, string $name, string $email, string $password): void
    {
        $tenant->run(function () use ($name, $email, $password): void {
            if (User::query()->where('email', $email)->exists()) {
                return;
            }

            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password, // User's 'hashed' cast hashes this on set
            ]);
        });

        $this->moduleManager->upgradeForTenant('core', $tenant);
    }
}
