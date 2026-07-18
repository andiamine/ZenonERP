<?php

namespace App\Foundation\Tenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web-group tenancy: central hosts pass through untouched; tenant subdomains get full
 * tenancy initialization BEFORE StartSession, so shared web routes (/sanctum/csrf-cookie
 * now, the Phase 4 SPA fallback later) read and write sessions in the tenant DB.
 * Unknown subdomains 404 via InitializeTenancyBySubdomain::$onFail.
 */
final class InitializeTenancyOnTenantHosts
{
    public function __construct(private readonly InitializeTenancyBySubdomain $inner) {}

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
