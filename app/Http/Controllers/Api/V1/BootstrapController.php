<?php

namespace App\Http\Controllers\Api\V1;

use App\Foundation\Frontend\GeneratedModuleRegistry;
use App\Foundation\Modules\ModuleRegistry;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SPA boot payload (CLAUDE.md §7), wrapped in `data` like every other endpoint.
 * Stubs fill in later: companies/current_company_id/settings (Phase 5 zenon/core),
 * remote_modules (Phase 7).
 */
class BootstrapController extends Controller
{
    public function __invoke(
        Request $request,
        ModuleRegistry $registry,
        GeneratedModuleRegistry $generatedRegistry,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 500); // unreachable: tenant-api group guarantees context

        return response()->json([
            'data' => [
                'user' => UserResource::make($user),
                'tenant' => [
                    'id' => $tenant->getTenantKey(),
                    'name' => $tenant->name,
                ],
                'companies' => [],
                'current_company_id' => null,
                'enabled_modules' => $registry->enabledFor($tenant),
                'remote_modules' => [],
                'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
                'settings' => (object) [],
                'locale' => (string) config('app.locale'),
                'registryHash' => $generatedRegistry->hash(),
            ],
        ]);
    }
}
