<?php

namespace App\Foundation;

/**
 * The single consumption point for `config('zenon.mode')` (CLAUDE.md §7: "Dual
 * deployment mode from day one"). Every mode-branching decision elsewhere in the
 * codebase must go through {@see self::current()} or {@see self::isStandalone()}
 * rather than reading the config key directly, so there is exactly one place that
 * decides what an unrecognized value means.
 *
 * Fails closed to {@see self::Saas}: a missing, blank, or garbled `zenon.mode` (a
 * misconfigured .env, a typo, a stripped env var during deploy) must never silently
 * grant the standalone single-tenant surface — the multi-tenant gates (subdomain
 * identification, central-domain checks) are the safer default to fall back to.
 */
enum DeploymentMode: string
{
    case Saas = 'saas';
    case Standalone = 'standalone';

    public static function current(): self
    {
        return self::tryFrom((string) config('zenon.mode')) ?? self::Saas;
    }

    public static function isStandalone(): bool
    {
        return self::current() === self::Standalone;
    }
}
