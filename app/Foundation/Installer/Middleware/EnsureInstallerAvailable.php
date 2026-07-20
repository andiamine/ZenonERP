<?php

namespace App\Foundation\Installer\Middleware;

use App\Foundation\DeploymentMode;
use App\Foundation\Installer\InstallerState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every /install* route (routes/installer.php, registered outside web/api in
 * bootstrap/app.php — no StartSession/EncryptCookies/CSRF/tenancy, so this is the ONLY
 * gate those routes get). 404s unless the wizard is actually available: standalone mode
 * AND not yet installed — the same "behaviorally invisible when not applicable" spirit as
 * EnsureModuleEnabled (CLAUDE.md §6 risk #1), applied to the installer surface itself.
 *
 * Unsafe methods (the wizard's state-changing steps) have no session to carry a CSRF
 * token, so this enforces same-origin via Origin/Referer instead — the browser-guaranteed
 * substitute. Origin wins when present (Referer is ignored in that case, per fetch()
 * semantics where a same-origin request always sets Origin on unsafe methods); Referer is
 * only consulted as a fallback. Both absent is rejected outright: a real browser request
 * always sends at least one of them for a same-origin POST, so total absence means a
 * non-browser or deliberately-stripped cross-origin caller — fail closed.
 */
final class EnsureInstallerAvailable
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly InstallerState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(DeploymentMode::isStandalone() && ! $this->state->isInstalled(), 404);

        if (! in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            $this->enforceSameOrigin($request);
        }

        return $next($request);
    }

    private function enforceSameOrigin(Request $request): void
    {
        $origin = $request->headers->get('Origin');
        $source = $origin ?? $request->headers->get('Referer');

        abort_if($source === null, 403);

        $sourceHost = parse_url($source, PHP_URL_HOST);

        abort_unless(
            is_string($sourceHost) && strcasecmp($sourceHost, $request->getHost()) === 0,
            403,
        );
    }
}
