<?php

namespace App\Http\Controllers\Installer;

use App\Foundation\Installer\Actions\CreateAdminUser;
use App\Foundation\Installer\Actions\RunCentralMigrations;
use App\Foundation\Installer\Actions\WriteEnvironment;
use App\Foundation\Installer\EnvWriter;
use App\Foundation\Installer\InstallerState;
use App\Foundation\Installer\RequirementsCheck;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Tenancy\Actions\CreateStandaloneTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Installer\AdminStepRequest;
use App\Http\Requests\Installer\DatabaseStepRequest;
use App\Http\Requests\Installer\TenantStepRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The standalone installer wizard's step API (CLAUDE.md §7 Phase 8 Task 6). Thin by
 * design — every action lives in app/Foundation/Installer; this class only validates
 * (via the FormRequests), delegates, and shapes the JSON response. Registered entirely
 * outside web/api (routes/installer.php, guarded solely by EnsureInstallerAvailable) —
 * no session, no CSRF, no tenancy.
 */
class InstallerController extends Controller
{
    public function requirements(RequirementsCheck $check): JsonResponse
    {
        $items = $check->run();
        $ok = ! in_array('fail', array_column($items, 'status'), true);

        return response()->json(['data' => ['ok' => $ok, 'items' => $items]]);
    }

    public function status(InstallerState $state, EnvWriter $envWriter, ModuleRegistry $registry): JsonResponse
    {
        $envPath = (string) (config('zenon.installer.env_path') ?? App::environmentFilePath());
        $env = $envWriter->read($envPath);
        $databaseDone = ($env['APP_KEY'] ?? '') !== '';

        $migrateDone = false;

        try {
            $migrateDone = Schema::hasTable('tenants')
                && Schema::hasTable('modules')
                && $this->firstPartyModulesInstalled($registry);
        } catch (Throwable) {
            $migrateDone = false;
        }

        $tenantDone = false;

        if ($migrateDone) {
            try {
                $tenantDone = Tenant::query()->count() > 0;
            } catch (Throwable) {
                $tenantDone = false;
            }
        }

        $adminDone = false;

        if ($tenantDone) {
            try {
                $tenant = Tenant::find('default');
                $adminDone = $tenant !== null && (bool) $tenant->run(fn () => User::query()->exists());
            } catch (Throwable) {
                $adminDone = false;
            }
        }

        return response()->json(['data' => ['steps' => [
            'database' => $databaseDone,
            'migrate' => $migrateDone,
            'tenant' => $tenantDone,
            'admin' => $adminDone,
            'finalize' => $state->isInstalled(),
        ]]]);
    }

    public function database(DatabaseStepRequest $request, WriteEnvironment $action): JsonResponse
    {
        $result = $action->handle($request->validated());

        if (! $result->success) {
            return response()->json([
                'error' => [
                    'type' => 'database_connection_failed',
                    'message' => 'Could not connect to one or both databases with the given credentials.',
                    'central' => $result->central->message,
                    'tenant' => $result->tenant->message,
                ],
            ], 422);
        }

        return response()->json(['data' => ['written' => true]]);
    }

    public function migrate(RunCentralMigrations $action): JsonResponse
    {
        $action->handle();

        return response()->json(['data' => ['migrated' => true]]);
    }

    public function tenant(TenantStepRequest $request, CreateStandaloneTenant $action, EnvWriter $envWriter): JsonResponse
    {
        $envPath = (string) (config('zenon.installer.env_path') ?? App::environmentFilePath());
        $dbName = $envWriter->read($envPath)['TENANT_DB_DATABASE'] ?? '';

        if ($dbName === '') {
            return response()->json([
                'error' => [
                    'type' => 'database_step_incomplete',
                    'message' => 'Run the database step first.',
                ],
            ], 422);
        }

        $tenant = $action->handle($request->string('name')->value(), $dbName, 'standalone');

        return response()->json(['data' => ['tenant_id' => $tenant->id]]);
    }

    public function admin(AdminStepRequest $request, CreateAdminUser $action): JsonResponse
    {
        $tenant = Tenant::find('default');

        if ($tenant === null) {
            return response()->json([
                'error' => [
                    'type' => 'tenant_step_incomplete',
                    'message' => 'Run the tenant step first.',
                ],
            ], 422);
        }

        $action->handle(
            $tenant,
            $request->string('name')->value(),
            $request->string('email')->value(),
            $request->string('password')->value(),
        );

        return response()->json(['data' => ['created' => true]]);
    }

    public function finalize(InstallerState $state): JsonResponse
    {
        $state->markInstalled();

        return response()->json(['data' => ['redirect' => '/']]);
    }

    /**
     * The migrate step is no longer just "did the schema land" — RunCentralMigrations
     * now also installs every discovered first-party module in the same request, so a
     * process killed between the two halves must resurface as migrate:false (not done),
     * letting a re-POST of /install/api/migrate converge instead of leaving the Tenant
     * step to silently enable zero modules.
     */
    private function firstPartyModulesInstalled(ModuleRegistry $registry): bool
    {
        return collect(array_keys($registry->discoveredFirstParty()))
            ->diff(array_keys($registry->installed()))
            ->isEmpty();
    }
}
