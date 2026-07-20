<?php

namespace App\Foundation\Tenancy;

/**
 * Pure parsing for the env-driven `tenancy.central_domains` config key (CLAUDE.md §7,
 * Phase 8) — no container, no IO.
 *
 * Distinguishes "unset" from "explicitly blank": a null $raw (TENANCY_CENTRAL_DOMAINS
 * absent from the environment entirely) falls back to $default, preserving the exact
 * pre-Phase-8 hardcoded list so the existing suite sees zero behavior change. A
 * non-null $raw that is blank (or blank after trimming/filtering) is a DELIBERATE
 * instruction, not a fallback trigger — the standalone installer writes
 * TENANCY_CENTRAL_DOMAINS="" on purpose so routes/api.php's central-domain foreach
 * registers zero central routes; silently substituting the default here would defeat
 * that.
 */
final class CentralDomains
{
    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    public static function parse(?string $raw, array $default): array
    {
        if ($raw === null) {
            return $default;
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $domain): bool => $domain !== '',
        ));
    }
}
