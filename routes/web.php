<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

/*
 * SPA catch-all (CLAUDE.md §7 boot). Reserved first segments NEVER serve the shell:
 *
 *   api/*     → unmatched API paths must keep the JSON 404 envelope (ApiExceptionRenderer)
 *   sanctum/* → csrf-cookie endpoint; unknown /sanctum/* paths must 404, not render HTML
 *   up        → framework health endpoint
 *   build/* storage/* vendor/* → assets; a missing chunk must 404, never HTML-200
 *   modules/* → prebuilt third-party addon assets (ModuleAssetController); a missing
 *               dist file must 404, never render the HTML shell
 *
 * Phase 8 adds `install/` here (the standalone-mode wizard). Keep this list in sync
 * with SpaFallbackTest.
 *
 * The exclusion lives in the route CONSTRAINT (negative lookahead, no ^/$ anchors —
 * Symfony wraps the requirement) rather than Route::fallback(), because web.php
 * registers BEFORE the tenant-api group (bootstrap/app.php `then:`) and module routes:
 * a fallback's catch-all placeholder relies on being registered last, which web.php
 * cannot guarantee. A constraint can never shadow later routes.
 *
 * Tenant hosts get tenant DB context (and tenant-DB sessions) via the
 * InitializeTenancyOnTenantHosts prepend on the web group; central hosts pass through.
 */
Route::get('/{path?}', SpaController::class)
    ->where('path', '(?!(?:api|sanctum|build|storage|vendor|modules)(?:/|$)|up$).*')
    ->name('spa');
