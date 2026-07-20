<?php

use App\Foundation\Api\ApiExceptionRenderer;
use App\Foundation\Company\SetCurrentCompany;
use App\Foundation\Installer\Middleware\EnsureInstallerAvailable;
use App\Foundation\Installer\Middleware\RedirectIfNotInstalled;
use App\Foundation\Modules\Middleware\EnsureModuleEnabled;
use App\Foundation\Tenancy\Middleware\InitializeTenancyByMode;
use App\Foundation\Tenancy\Middleware\InitializeTenancyOnTenantHosts;
use App\Http\Controllers\ModuleAssetController;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
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
            // The explicit priority list below sorts PreventAccess → Initialize first.
            // InitializeTenancyByMode picks subdomain (saas) vs domain (standalone)
            // identification per DeploymentMode (CLAUDE.md §7, Phase 8 Task 3).
            Route::middleware([
                'api',
                InitializeTenancyByMode::class,
                PreventAccessFromCentralDomains::class,
            ])->prefix('api')->group(base_path('routes/tenant-api.php'));

            // Prebuilt third-party addon dist files (CLAUDE.md §7). Deliberately outside
            // BOTH the 'web' and 'api' middleware groups: these are static assets, host-
            // agnostic (identical bytes on every tenant subdomain), and must never start a
            // session (StartSession) or touch tenancy — a stray Set-Cookie or DB lookup on
            // an asset request would be a bug. ModuleAssetController owns the traversal
            // defense; the where() below is the first (not the only) layer.
            Route::get('modules/thirdparty/{folder}/dist/{path}', ModuleAssetController::class)
                ->where(['folder' => '[A-Za-z0-9]+', 'path' => '[A-Za-z0-9._/\-]+'])
                ->name('module.asset');

            // Standalone-mode installer wizard (CLAUDE.md §7 Phase 8). Deliberately outside
            // BOTH the 'web' and 'api' groups: a fresh extract has an empty APP_KEY and no
            // database, so StartSession/EncryptCookies/CSRF/tenancy must never run here —
            // EnsureInstallerAvailable (404 unless standalone + uninstalled, same-origin
            // check on unsafe methods) is the sole gate. Mirrors the tenant-api registration
            // pattern above.
            Route::middleware([EnsureInstallerAvailable::class])
                ->prefix('install')
                ->group(base_path('routes/installer.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA cookie mode: prepends EnsureFrontendRequestsAreStateful to the api group.
        $middleware->statefulApi();

        // Shared web routes (/sanctum/csrf-cookie now, the Phase 4 SPA fallback later) get
        // tenant context on tenant subdomains before StartSession touches the DB.
        // RedirectIfNotInstalled (Phase 8) runs first: a fresh standalone extract has no
        // APP_KEY/DB/tenant, so nothing past it may run until the wizard has provisioned it.
        $middleware->web(prepend: [RedirectIfNotInstalled::class, InitializeTenancyOnTenantHosts::class]);

        $middleware->alias([
            'module.enabled' => EnsureModuleEnabled::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
        ]);

        // EXPLICIT priority list — load-bearing. EnsureFrontendRequestsAreStateful is NOT in
        // the framework's default $middlewarePriority, so without this it stays at api-group
        // position 0 and its nested StartSession runs BEFORE tenancy initialization: sessions
        // would silently land in the CENTRAL DB on tenant hosts. Tenancy classes listed here
        // also make TenancyServiceProvider's prepends no-ops (in_array-guarded), keeping one
        // authoritative ordering. Entries below the Sanctum line are the framework defaults
        // copied from Illuminate\Foundation\Http\Kernel::$middlewarePriority, with
        // SetCurrentCompany (§8) inserted immediately after AuthenticatesRequests — it must
        // sort after route-level `auth:sanctum` so it sees the authenticated user.
        //
        // EnsureModuleEnabled (§5/§6 risk #1: "a disabled module must be behaviorally
        // invisible") is ALSO listed here, right after tenancy init and before Sanctum's
        // stateful/session/auth chain — without this it's unlisted, and Laravel's
        // SortedMiddleware only reorders middleware that ARE in the priority map relative to
        // each other, so an unlisted module.enabled gets dragged wherever AuthenticatesRequests
        // (auth:sanctum, matched via its AuthenticatesRequests interface) happens to land —
        // observed as a disabled Core route 401ing (auth ran first) instead of 404ing.
        // EnsureModuleEnabled only needs tenant() resolved, never auth/session state, so
        // running it first is always safe and makes a disabled module 404 before the
        // request even touches sessions or authentication.
        //
        // RedirectIfNotInstalled (Phase 8) is inserted at the very top, ahead of even
        // PreventAccessFromCentralDomains: pure config+filesystem, must run before anything
        // that could touch a nonexistent database on a fresh standalone extract. Insertion
        // only — the rest of this list is unchanged and unreordered (see the web-group
        // prepend comment above; Phase 8 Task 3 note applies here too).
        $middleware->priority([
            RedirectIfNotInstalled::class,
            PreventAccessFromCentralDomains::class,
            InitializeTenancyByDomain::class,
            InitializeTenancyBySubdomain::class,
            InitializeTenancyByDomainOrSubdomain::class,
            InitializeTenancyByPath::class,
            InitializeTenancyByRequestData::class,
            InitializeTenancyByMode::class,
            InitializeTenancyOnTenantHosts::class,
            EnsureModuleEnabled::class,
            EnsureFrontendRequestsAreStateful::class,
            HandlePrecognitiveRequests::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            SetCurrentCompany::class,
            ThrottleRequests::class,
            ThrottleRequestsWithRedis::class,
            AuthenticatesSessions::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // §8 single error envelope renderer — api/* only; web keeps framework defaults.
        $exceptions->render(
            fn (Throwable $e, Request $request) => $request->is('api/*')
                ? ApiExceptionRenderer::render($e, $request)
                : null,
        );
    })->create();
