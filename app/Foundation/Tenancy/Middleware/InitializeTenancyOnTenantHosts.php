<?php

namespace App\Foundation\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web-group tenancy: central hosts pass through untouched; tenant hosts get full
 * tenancy initialization BEFORE StartSession, so shared web routes (/sanctum/csrf-cookie
 * now, the Phase 4 SPA fallback later) read and write sessions in the tenant DB. The
 * inner delegate is mode-aware (CLAUDE.md §7, Phase 8 Task 3): subdomain identification
 * in saas, full-domain identification in standalone. Unknown hosts 404 via the
 * delegate's underlying stancl `$onFail` (set for both identification strategies in
 * TenancyServiceProvider::boot).
 */
final class InitializeTenancyOnTenantHosts
{
    public function __construct(private readonly InitializeTenancyByMode $inner) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $centralDomains */
        $centralDomains = config('tenancy.central_domains');

        if (in_array($request->getHost(), $centralDomains, true)) {
            return $next($request);
        }

        return $this->inner->handle($request, $next);
    }
}
