<?php

namespace App\Foundation\Installer\Middleware;

use App\Foundation\DeploymentMode;
use App\Foundation\Installer\InstallerState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prepended to the web group BEFORE InitializeTenancyOnTenantHosts (bootstrap/app.php)
 * and placed at the very top of the middleware priority list: a fresh standalone extract
 * has no APP_KEY, no database, and no tenant, so nothing past this point (tenancy
 * initialization, sessions, auth) may run until the wizard has provisioned it. Reads only
 * config + the filesystem (InstallerState), never the database, so it can never itself
 * 500 on that fresh extract — the one thing that MUST work unconditionally.
 *
 * /install* paths never actually reach this middleware in practice: routes/installer.php
 * is registered entirely outside the web group (bootstrap/app.php `then:` closure), and
 * the SPA catch-all's lookahead (routes/web.php) excludes the `install` segment. That is
 * structural, not enforced here — this class deliberately does not special-case `install`,
 * to keep the rule a single unconditional "standalone + uninstalled -> redirect".
 */
final class RedirectIfNotInstalled
{
    public function __construct(private readonly InstallerState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (DeploymentMode::isStandalone() && ! $this->state->isInstalled()) {
            return redirect('/install');
        }

        return $next($request);
    }
}
