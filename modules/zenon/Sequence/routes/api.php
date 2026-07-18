<?php

use Illuminate\Support\Facades\Route;
use Modules\Sequence\Http\Controllers\Api\V1\SequencesController;

/*
 * Auto-wrapped by the Foundation base provider (ModuleServiceProvider::mapApiRoutes):
 * every route below already sits behind /api/v1/sequence, the 'api' group, subdomain
 * tenancy init, module.enabled:sequence, and SetCurrentCompany. auth:sanctum is added
 * here — sequence administration always requires an authenticated tenant user.
 *
 * The numbering ENGINE (SequenceGenerator) is a container binding consumed in-process by
 * other modules; it has no HTTP surface. These routes only administer the counters.
 */
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('sequences', [SequencesController::class, 'index'])
        ->middleware('permission:sequence.sequences.view')->name('sequences.index');

    Route::get('definitions', [SequencesController::class, 'definitions'])
        ->middleware('permission:sequence.sequences.view')->name('definitions.index');

    Route::patch('sequences/{sequence}', [SequencesController::class, 'update'])
        ->middleware('permission:sequence.sequences.update')->name('sequences.update');
});
