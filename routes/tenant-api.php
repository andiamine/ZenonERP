<?php

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

Route::get('/v1/ping', PingController::class);
