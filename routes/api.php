<?php

use App\Http\Controllers\Api\V1\Central\LoginController;
use App\Http\Controllers\Api\V1\Central\LogoutController;
use App\Http\Controllers\Api\V1\Central\MeController;
use App\Http\Controllers\Api\V1\SignupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API routes
|--------------------------------------------------------------------------
|
| Registered under /api on the central domains only — tenant subdomains
| must never expose platform endpoints. Auth uses the `central` guard
| (platform operators, central users table).
|
*/

/** @var list<string> $centralDomains */
$centralDomains = config('tenancy.central_domains');

foreach ($centralDomains as $domain) {
    Route::domain($domain)->group(function (): void {
        Route::post('/v1/auth/login', LoginController::class)->middleware('throttle:10,1');
        Route::post('/v1/auth/logout', LogoutController::class)->middleware('auth:central');
        Route::get('/v1/auth/me', MeController::class)->middleware('auth:central');

        // Operator-gated (decided Phase 3): public self-service signup is a deliberate
        // later feature (email verification, billing) — not an open endpoint.
        Route::post('/v1/signup', SignupController::class)->middleware(['auth:central', 'throttle:10,1']);
    });
}
