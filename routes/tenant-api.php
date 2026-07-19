<?php

use App\Foundation\Company\SetCurrentCompany;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\BootstrapController;
use App\Http\Controllers\Api\V1\PingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant API routes
|--------------------------------------------------------------------------
|
| Registered under /api for {tenant}.zenonerp.test — wrapped in subdomain
| tenancy initialization by bootstrap/app.php. Module routes will be
| registered by the module system from Phase 2 onwards.
|
*/

Route::prefix('/v1')->group(function (): void {
    Route::get('/ping', PingController::class);

    Route::post('/auth/login', LoginController::class)->middleware('throttle:10,1');
    Route::post('/auth/logout', LogoutController::class)->middleware('auth:sanctum');
    Route::get('/auth/me', MeController::class)->middleware('auth:sanctum');

    // SetCurrentCompany honors the SPA's X-Company-Id header (validated against the user's
    // memberships → 403 on a foreign id) so the boot payload reflects the ACTIVE company,
    // not always the default. It's in bootstrap/app.php's priority list right after
    // AuthenticatesRequests, so it sorts after auth:sanctum and sees the authenticated user.
    Route::get('/bootstrap', BootstrapController::class)->middleware(['auth:sanctum', SetCurrentCompany::class]);
});
