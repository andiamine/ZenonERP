<?php

namespace App\Http\Controllers\Api\V1;

use App\Foundation\Company\CurrentCompany;
use App\Foundation\Frontend\GeneratedModuleRegistry;
use App\Foundation\Modules\ModuleRegistry;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Contracts\Companies\CompanyDirectory;
use Modules\Core\Contracts\Settings\SettingsReader;

/**
 * SPA boot payload (CLAUDE.md §7), wrapped in `data` like every other endpoint.
 * remote_modules is populated from ModuleRegistry::remoteModulesFor() (Phase 7).
 *
 * companies/current_company_id/settings only import from Modules\Core\Contracts\* — the
 * one place the host app is allowed to reach into a module (CLAUDE.md §2 arch rule: never
 * Models/Services). Gated on the kernel module (config('zenon.kernel_module', 'core'))
 * being enabled for the tenant AND its CompanyDirectory binding actually being present —
 * both false for any core-less tenant, which is why the stub values below are the
 * correct answer in that case, not a placeholder.
 */
class BootstrapController extends Controller
{
    public function __invoke(
        Request $request,
        ModuleRegistry $registry,
        GeneratedModuleRegistry $generatedRegistry,
        CurrentCompany $currentCompany,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $tenant = tenant();
        abort_unless($tenant instanceof Tenant, 500); // unreachable: tenant-api group guarantees context

        $companies = [];
        $currentCompanyId = null;
        $settings = (object) [];

        if ($registry->isEnabledForCurrentTenant(config('zenon.kernel_module', 'core'))
            && app()->bound(CompanyDirectory::class)) {
            $directory = app(CompanyDirectory::class);
            $companies = array_map(fn ($c) => $c->toArray(), $directory->companiesFor($user));
            // SetCurrentCompany (on this route) already resolved the X-Company-Id header — or
            // the user's default when absent — into CurrentCompany, validating membership.
            // Honor it for BOTH the reported id and the settings map so the switcher works.
            $currentCompanyId = $currentCompany->id() ?? $directory->defaultCompanyIdFor($user);
            $settings = (object) app(SettingsReader::class)->all($currentCompanyId);
        }

        return response()->json([
            'data' => [
                'user' => UserResource::make($user),
                'tenant' => [
                    'id' => $tenant->getTenantKey(),
                    'name' => $tenant->name,
                ],
                'companies' => $companies,
                'current_company_id' => $currentCompanyId,
                'enabled_modules' => $registry->enabledFor($tenant),
                'remote_modules' => $registry->remoteModulesFor($tenant),
                'permissions' => $user->hasRole('admin')
                    ? ['*']
                    : $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
                'settings' => $settings,
                'locale' => (string) config('app.locale'),
                'registryHash' => $generatedRegistry->hash(),
                'platform_version' => (string) config('zenon.platform_version'),
            ],
        ]);
    }
}
