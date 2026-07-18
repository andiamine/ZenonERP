<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Api\V1\CompaniesController;
use Modules\Core\Http\Controllers\Api\V1\CompanyUsersController;
use Modules\Core\Http\Controllers\Api\V1\CurrenciesController;
use Modules\Core\Http\Controllers\Api\V1\CurrencyRatesController;
use Modules\Core\Http\Controllers\Api\V1\PermissionsController;
use Modules\Core\Http\Controllers\Api\V1\RolePermissionsController;
use Modules\Core\Http\Controllers\Api\V1\RolesController;
use Modules\Core\Http\Controllers\Api\V1\SettingsController;
use Modules\Core\Http\Controllers\Api\V1\TeamMembersController;
use Modules\Core\Http\Controllers\Api\V1\TeamsController;
use Modules\Core\Http\Controllers\Api\V1\UserRolesController;
use Modules\Core\Http\Controllers\Api\V1\UsersController;

/*
 * Wrapped by the Foundation base provider (ModuleServiceProvider::mapApiRoutes): every
 * route below already sits behind /api/v1/core, the 'api' group, subdomain tenancy init,
 * module.enabled:core, and SetCurrentCompany. auth:sanctum is added here because it's
 * per-module — not every module route needs authentication, Core's always does.
 *
 * NO Policy classes (deliberate Phase 5 decision, CLAUDE.md §2): authorization is the
 * route-level `permission:` middleware below; state invariants (default/last company,
 * self-delete, admin role, referenced currency) are enforced inside the Actions
 * (app/Actions) that these controllers call, not in a Policy layer.
 */
Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('companies')->name('companies.')->group(function (): void {
        Route::get('/', [CompaniesController::class, 'index'])
            ->middleware('permission:core.companies.view')->name('index');
        Route::get('/{company}', [CompaniesController::class, 'show'])
            ->middleware('permission:core.companies.view')->name('show');
        Route::post('/', [CompaniesController::class, 'store'])
            ->middleware('permission:core.companies.create')->name('store');
        Route::patch('/{company}', [CompaniesController::class, 'update'])
            ->middleware('permission:core.companies.update')->name('update');
        Route::delete('/{company}', [CompaniesController::class, 'destroy'])
            ->middleware('permission:core.companies.delete')->name('destroy');
        Route::put('/{company}/users', CompanyUsersController::class)
            ->middleware('permission:core.companies.update')->name('users.sync');
    });

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/', [SettingsController::class, 'index'])
            ->middleware('permission:core.settings.view')->name('index');
        Route::get('/definitions', [SettingsController::class, 'definitions'])
            ->middleware('permission:core.settings.view')->name('definitions');
        Route::put('/', [SettingsController::class, 'update'])
            ->middleware('permission:core.settings.update')->name('update');
    });

    Route::prefix('currencies')->name('currencies.')->group(function (): void {
        Route::get('/', [CurrenciesController::class, 'index'])
            ->middleware('permission:core.currencies.view')->name('index');
        Route::get('/{currency}', [CurrenciesController::class, 'show'])
            ->middleware('permission:core.currencies.view')->name('show');
        Route::post('/', [CurrenciesController::class, 'store'])
            ->middleware('permission:core.currencies.create')->name('store');
        Route::patch('/{currency}', [CurrenciesController::class, 'update'])
            ->middleware('permission:core.currencies.update')->name('update');
        Route::delete('/{currency}', [CurrenciesController::class, 'destroy'])
            ->middleware('permission:core.currencies.delete')->name('destroy');
        Route::get('/{currency}/rates', [CurrencyRatesController::class, 'index'])
            ->middleware('permission:core.currencies.view')->name('rates.index');
        Route::post('/{currency}/rates', [CurrencyRatesController::class, 'store'])
            ->middleware('permission:core.currencies.update')->name('rates.store');
    });

    Route::prefix('users')->name('users.')->group(function (): void {
        Route::get('/', [UsersController::class, 'index'])
            ->middleware('permission:core.users.view')->name('index');
        Route::get('/{user}', [UsersController::class, 'show'])
            ->middleware('permission:core.users.view')->name('show');
        Route::post('/', [UsersController::class, 'store'])
            ->middleware('permission:core.users.create')->name('store');
        Route::patch('/{user}', [UsersController::class, 'update'])
            ->middleware('permission:core.users.update')->name('update');
        Route::delete('/{user}', [UsersController::class, 'destroy'])
            ->middleware('permission:core.users.delete')->name('destroy');
        Route::put('/{user}/roles', UserRolesController::class)
            ->middleware('permission:core.roles.assign')->name('roles.sync');
    });

    Route::prefix('roles')->name('roles.')->group(function (): void {
        Route::get('/', [RolesController::class, 'index'])
            ->middleware('permission:core.roles.view')->name('index');
        Route::get('/{role}', [RolesController::class, 'show'])
            ->middleware('permission:core.roles.view')->name('show');
        Route::post('/', [RolesController::class, 'store'])
            ->middleware('permission:core.roles.create')->name('store');
        Route::patch('/{role}', [RolesController::class, 'update'])
            ->middleware('permission:core.roles.update')->name('update');
        Route::delete('/{role}', [RolesController::class, 'destroy'])
            ->middleware('permission:core.roles.delete')->name('destroy');
        Route::put('/{role}/permissions', RolePermissionsController::class)
            ->middleware('permission:core.roles.update')->name('permissions.sync');
    });

    Route::get('/permissions', PermissionsController::class)
        ->middleware('permission:core.roles.view')->name('permissions.index');

    Route::prefix('teams')->name('teams.')->group(function (): void {
        Route::get('/', [TeamsController::class, 'index'])
            ->middleware('permission:core.teams.view')->name('index');
        Route::get('/{team}', [TeamsController::class, 'show'])
            ->middleware('permission:core.teams.view')->name('show');
        Route::post('/', [TeamsController::class, 'store'])
            ->middleware('permission:core.teams.create')->name('store');
        Route::patch('/{team}', [TeamsController::class, 'update'])
            ->middleware('permission:core.teams.update')->name('update');
        Route::delete('/{team}', [TeamsController::class, 'destroy'])
            ->middleware('permission:core.teams.delete')->name('destroy');
        Route::put('/{team}/users', TeamMembersController::class)
            ->middleware('permission:core.teams.update')->name('users.sync');
    });
});
