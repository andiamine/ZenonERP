<?php

namespace App\Foundation\Tenancy\Middleware;

use App\Foundation\DeploymentMode;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode-aware tenancy identification (CLAUDE.md §7, Phase 8 Task 3): the one place that
 * decides which stancl identification strategy runs. Saas keeps subdomain
 * identification (`{tenant}.zenonerp.test`) unchanged. Standalone identifies its single
 * tenant by a real `domains` row — the installed host — via stock
 * InitializeTenancyByDomain; this is deliberately NOT "any host is THE tenant" (that
 * would accept a spoofed Host header for any hostname reaching the server).
 *
 * Delegates to the two stock stancl middleware instances rather than re-implementing
 * resolution, so their `$onFail` handling (TenancyServiceProvider::boot — both classes
 * 404 on failure) and resolver wiring stay authoritative. Mirrors the delegation style
 * of InitializeTenancyOnTenantHosts, whose inner delegate is this class.
 */
final class InitializeTenancyByMode
{
    public function __construct(
        private readonly InitializeTenancyBySubdomain $subdomain,
        private readonly InitializeTenancyByDomain $domain,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (DeploymentMode::isStandalone()) {
            return $this->domain->handle($request, $next);
        }

        return $this->subdomain->handle($request, $next);
    }
}
