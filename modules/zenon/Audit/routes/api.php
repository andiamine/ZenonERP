<?php

use Illuminate\Support\Facades\Route;
use Modules\Audit\Http\Controllers\Api\V1\ActivitiesController;

/*
 * Auto-wrapped by the Foundation base provider (ModuleServiceProvider::mapApiRoutes):
 * every route below already sits behind /api/v1/audit, the 'api' group, subdomain
 * tenancy init, module.enabled:audit, and SetCurrentCompany.
 */
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('activities', [ActivitiesController::class, 'index'])
        ->middleware('permission:audit.activities.view')->name('activities.index');
});
