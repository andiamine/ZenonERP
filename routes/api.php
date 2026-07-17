<?php

use App\Http\Controllers\Api\V1\SignupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API routes
|--------------------------------------------------------------------------
|
| Registered under /api on the central domains only — tenant subdomains
| must never expose platform endpoints.
|
*/

/** @var list<string> $centralDomains */
$centralDomains = config('tenancy.central_domains');

foreach ($centralDomains as $domain) {
    Route::domain($domain)->group(function (): void {
        Route::post('/v1/signup', SignupController::class)->middleware('throttle:10,1');
    });
}
