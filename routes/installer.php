<?php

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
| Task 5 ships only the GET stub below. Task 6 adds the step API:
| GET /install/api/{status,requirements}, POST /install/api/{database,migrate,tenant,
| admin,finalize}. Task 7 replaces the GET / stub with the wizard Blade view.
|
*/

Route::get('/', fn () => response('ZenonERP Installer'))->name('installer.show');

// Task-5 test seam ONLY: exercises EnsureInstallerAvailable's same-origin check on an
// unsafe (POST) method. A plain `POST /install` can't be used for that — with no POST
// route registered there yet, Laravel throws MethodNotAllowedHttpException while matching
// the route (AbstractRouteCollection::handleMatchedRoute), which happens BEFORE any
// group middleware runs for an unmatched route — so the 403 would never be reachable to
// assert. This stub is superseded outright once Task 6 adds real POST /install/api/*
// endpoints (delete it then, don't keep it alongside them).
Route::post('api/ping', fn () => response()->noContent())->name('installer.ping');
