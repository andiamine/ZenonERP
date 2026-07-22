<?php

use App\Http\Controllers\Installer\InstallerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Installer routes
|--------------------------------------------------------------------------
|
| Registered OUTSIDE the web/api groups (bootstrap/app.php `then:` closure), wrapped only
| in EnsureInstallerAvailable — no StartSession, no EncryptCookies, no CSRF, no tenancy, so
| this boots with an empty APP_KEY and no database (CLAUDE.md §7 Phase 8).
|
| GET / renders the self-contained wizard view (resources/views/installer.blade.php —
| CLAUDE.md §7 Phase 8 Task 7, the documented amendment to "app.blade.php is the ONLY
| Blade file": inline CSS + vanilla JS, zero build, so it renders before any build/DB
| exists). Everything below is Task 6's step API: GET /install/api/{status,requirements},
| POST /install/api/{database,migrate,tenant,admin,finalize} — see
| App\Http\Controllers\Installer\InstallerController.
|
*/

Route::get('/', fn () => view('installer'))->name('installer.show');

Route::get('api/status', [InstallerController::class, 'status'])->name('installer.status');
Route::get('api/requirements', [InstallerController::class, 'requirements'])->name('installer.requirements');

Route::post('api/database', [InstallerController::class, 'database'])->name('installer.database');
Route::post('api/migrate', [InstallerController::class, 'migrate'])->name('installer.migrate');
Route::post('api/tenant', [InstallerController::class, 'tenant'])->name('installer.tenant');
Route::post('api/admin', [InstallerController::class, 'admin'])->name('installer.admin');
Route::post('api/finalize', [InstallerController::class, 'finalize'])->name('installer.finalize');
