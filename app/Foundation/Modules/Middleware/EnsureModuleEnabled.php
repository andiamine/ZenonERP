<?php

namespace App\Foundation\Modules\Middleware;

use App\Foundation\Modules\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One of the three enablement gates (route middleware; hook bus and listener decorator
 * follow in Phase 6) — all of them consult ONLY ModuleRegistry (risk #1).
 */
final class EnsureModuleEnabled
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function handle(Request $request, Closure $next, string $alias): Response
    {
        // Also 404s when no tenant is initialized (central domain, unknown host).
        abort_unless($this->registry->isEnabledForCurrentTenant($alias), 404);

        return $next($request);
    }
}
