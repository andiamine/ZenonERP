<?php

namespace App\Foundation\Company;

use App\Foundation\Company\Contracts\CompanyResolver;
use App\Foundation\Modules\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the X-Company-Id header (or the user's default company) into CurrentCompany
 * for the duration of the request (CLAUDE.md §8). Appended to every module route by
 * ModuleServiceProvider — a per-module opt-in would be exactly the risk-#1 leak CLAUDE.md
 * warns about.
 *
 * Runs company-blind (CurrentCompany stays null = unscoped, `$next` called untouched)
 * whenever the kernel isn't in play yet: no tenant context, the kernel module isn't
 * enabled for the tenant, or no CompanyResolver is bound. All three are simultaneously
 * true for fixture-module tests and any core-less tenant today — the companies table
 * may not even exist — so this is not defensive paranoia, it is the common case pre-Core.
 *
 * `zenon.kernel_module` (config/zenon.php) exists purely for testability: production
 * always gates on the real "core" alias, but Core doesn't exist until a later Phase 5
 * task, so tests point this at a fixture module (DummyCore, alias "dummycore") to
 * exercise the positive path honestly today, without special-casing test code here.
 */
final class SetCurrentCompany
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly CurrentCompany $currentCompany,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $kernelModule = (string) config('zenon.kernel_module', 'core');

        if (tenant() === null
            || ! $this->registry->isEnabledForCurrentTenant($kernelModule)
            || ! app()->bound(CompanyResolver::class)
        ) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        /** @var CompanyResolver $resolver */
        $resolver = app(CompanyResolver::class);

        $header = $request->header('X-Company-Id');

        if ($header === null) {
            $this->currentCompany->set($resolver->defaultCompanyIdFor($user));

            return $next($request);
        }

        $companyId = filter_var($header, FILTER_VALIDATE_INT);

        abort_unless(
            $companyId !== false && $companyId > 0 && in_array($companyId, $resolver->companyIdsFor($user), true),
            403,
            'Invalid company.',
        );

        $this->currentCompany->set($companyId);

        return $next($request);
    }
}
