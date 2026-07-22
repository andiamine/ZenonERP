<?php

namespace App\Foundation\Installer\Actions;

use App\Foundation\Installer\DatabaseConnectionProbe;
use App\Foundation\Installer\EnvWriter;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\App;

/**
 * The installer's "database" step (CLAUDE.md §7 Phase 8 Task 6): probes BOTH databases
 * first (central; tenant — tenant creds default to the matching central value when
 * blank, the common cPanel/Plesk case of one DB user granted on both pre-created DBs)
 * and only writes `.env` if both connect. Never reloads config in-process afterward —
 * production picks the new .env up on the next request's fresh boot (classic PHP
 * per-request bootstrapping re-reads .env every time unless a stale config cache is
 * present, which RequirementsCheck clears before this step can even run).
 *
 * $data is the validated payload from DatabaseStepRequest: app_name, app_url, an
 * optional `driver` (defaults 'mysql' — the only real production shape; 'sqlite' exists
 * purely so InstallerFlowTest can walk this whole step without a real MySQL server),
 * and `central`/`tenant` arrays each shaped { database (required), host?, port?,
 * username?, password? }.
 */
final class WriteEnvironment
{
    public function __construct(
        private readonly DatabaseConnectionProbe $probe,
        private readonly EnvWriter $writer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): WriteEnvironmentResult
    {
        $driver = is_string($data['driver'] ?? null) && $data['driver'] !== '' ? $data['driver'] : 'mysql';

        /** @var array<string, mixed> $centralInput */
        $centralInput = $data['central'];
        /** @var array<string, mixed> $tenantInput */
        $tenantInput = $data['tenant'];

        $centralConfig = $this->connectionConfig($driver, $centralInput);
        $tenantConfig = $this->connectionConfig($driver, $this->resolveTenantCreds($tenantInput, $centralInput));

        $centralProbe = $this->probe->probe($this->probeConfig($driver, $centralConfig));
        $tenantProbe = $this->probe->probe($this->probeConfig($driver, $tenantConfig));

        if (! $centralProbe->success || ! $tenantProbe->success) {
            return new WriteEnvironmentResult(false, $centralProbe, $tenantProbe);
        }

        $path = (string) (config('zenon.installer.env_path') ?? App::environmentFilePath());
        $appUrl = (string) $data['app_url'];
        $host = parse_url($appUrl, PHP_URL_HOST);
        $host = is_string($host) && $host !== '' ? $host : $appUrl;

        $this->writer->write($path, [
            'APP_KEY' => 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher'))),
            'APP_NAME' => (string) $data['app_name'],
            'APP_URL' => $appUrl,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'ZENON_MODE' => 'standalone',
            // Literal blank value — CentralDomains::parse() treats a non-null-but-blank
            // TENANCY_CENTRAL_DOMAINS as "zero central routes", not "use the default list"
            // (CLAUDE.md §7 Task 1). This line must exist even though its value is empty.
            'TENANCY_CENTRAL_DOMAINS' => '',
            'SANCTUM_STATEFUL_DOMAINS' => $host,
            'SESSION_DOMAIN' => 'null',
            'DB_CONNECTION' => $driver,
            'DB_HOST' => (string) ($centralConfig['host'] ?? ''),
            'DB_PORT' => (string) ($centralConfig['port'] ?? ''),
            'DB_DATABASE' => (string) ($centralConfig['database'] ?? ''),
            'DB_USERNAME' => (string) ($centralConfig['username'] ?? ''),
            'DB_PASSWORD' => (string) ($centralConfig['password'] ?? ''),
            'TENANT_DB_HOST' => (string) ($tenantConfig['host'] ?? ''),
            'TENANT_DB_PORT' => (string) ($tenantConfig['port'] ?? ''),
            'TENANT_DB_DATABASE' => (string) ($tenantConfig['database'] ?? ''),
            'TENANT_DB_USERNAME' => (string) ($tenantConfig['username'] ?? ''),
            'TENANT_DB_PASSWORD' => (string) ($tenantConfig['password'] ?? ''),
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'DB_CACHE_CONNECTION' => 'mysql',
            'DB_QUEUE_CONNECTION' => 'mysql',
        ]);

        return new WriteEnvironmentResult(true, $centralProbe, $tenantProbe);
    }

    /**
     * Tenant host/port/username/password fall back to the matching central value when
     * blank or absent; `database` is always required and never inherited.
     *
     * @param  array<string, mixed>  $tenantInput
     * @param  array<string, mixed>  $centralInput
     * @return array<string, mixed>
     */
    private function resolveTenantCreds(array $tenantInput, array $centralInput): array
    {
        $resolved = ['database' => $tenantInput['database']];

        foreach (['host', 'port', 'username', 'password'] as $key) {
            $value = $tenantInput[$key] ?? null;
            $resolved[$key] = ($value === null || $value === '') ? ($centralInput[$key] ?? null) : $value;
        }

        return $resolved;
    }

    /**
     * The connection config as WRITTEN to .env / probed with for mysql/mariadb — raw,
     * exactly what the admin typed. Never resolves sqlite paths here: that happens only
     * in probeConfig(), kept separate so a sqlite TENANT_DB_DATABASE written to .env
     * stays the bare filename CreateStandaloneTenant expects (SQLiteDatabaseManager
     * prefixes it with database_path() itself — double-prefixing would break it).
     *
     * @param  array<string, mixed>  $creds
     * @return array<string, mixed>
     */
    private function connectionConfig(string $driver, array $creds): array
    {
        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => (string) $creds['database'],
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => $driver,
            'host' => $creds['host'] ?? '127.0.0.1',
            'port' => $creds['port'] ?? '3306',
            'database' => (string) $creds['database'],
            'username' => $creds['username'] ?? 'root',
            'password' => $creds['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
    }

    /**
     * The config actually PROBED with. Identical to connectionConfig() for mysql/
     * mariadb (no such thing as a "relative" mysql database name). For sqlite — a test/
     * dev-only driver, CLAUDE.md never ships it in production — a relative filename is
     * resolved against database_path(), mirroring exactly what
     * Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager::makeConnectionConfig()
     * does for the tenant DB later, so the probe touches the exact same file the rest of
     * the wizard will actually use.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function probeConfig(string $driver, array $config): array
    {
        if ($driver !== 'sqlite') {
            return $config;
        }

        $database = (string) $config['database'];

        if ($database === '' || $database === ':memory:' || $this->isAbsolutePath($database)) {
            return $config;
        }

        $config['database'] = database_path($database);

        return $config;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }
}
