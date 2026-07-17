<?php

use App\Foundation\Modules\Middleware\EnsureModuleEnabled;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // central API — 'api' middleware group + /api prefix
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Tenant API: same 'api' group + /api prefix, plus tenancy middleware.
            // TenancyServiceProvider sorts PreventAccess → Initialize first via middleware priority.
            Route::middleware([
                'api',
                InitializeTenancyBySubdomain::class,
                PreventAccessFromCentralDomains::class,
            ])->prefix('api')->group(base_path('routes/tenant-api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'module.enabled' => EnsureModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
