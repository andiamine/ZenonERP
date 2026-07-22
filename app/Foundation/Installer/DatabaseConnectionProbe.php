<?php

namespace App\Foundation\Installer;

use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

/**
 * Connectivity preflight for the installer's database step (CLAUDE.md §7 Phase 8 Task
 * 6) — registers a throwaway connection config, opens a real PDO handle, and tears the
 * connection back down. Driver-agnostic by construction: it accepts a raw Laravel
 * connection config array (whatever `connectionConfig()` builds in Actions\
 * WriteEnvironment) and never assumes 'mysql' — production probes mysql/mariadb, the
 * test suite probes sqlite, both go through the exact same code path.
 *
 * A short PDO connect timeout is enforced for mysql/mariadb so a bogus/unroutable host
 * fails fast instead of hanging for the platform's default TCP timeout — the wizard is
 * a single synchronous HTTP request on shared hosting (max_execution_time matters).
 */
final class DatabaseConnectionProbe
{
    private const CONNECTION_NAME = '__installer_probe';

    private const CONNECT_TIMEOUT_SECONDS = 5;

    /**
     * @param  array<string, mixed>  $config
     */
    public function probe(array $config): ProbeResult
    {
        if (in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $config['options'] = ($config['options'] ?? []) + [PDO::ATTR_TIMEOUT => self::CONNECT_TIMEOUT_SECONDS];
        }

        config(['database.connections.'.self::CONNECTION_NAME => $config]);

        try {
            DB::connection(self::CONNECTION_NAME)->getPdo();

            return ProbeResult::success();
        } catch (Throwable $e) {
            return ProbeResult::failure($e->getMessage());
        } finally {
            DB::purge(self::CONNECTION_NAME);

            /** @var array<string, mixed> $connections */
            $connections = config('database.connections');
            unset($connections[self::CONNECTION_NAME]);
            config(['database.connections' => $connections]);
        }
    }
}
