<?php

namespace Modules\Sequence\Console;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Modules\Sequence\Contracts\SequenceGenerator;

/**
 * Hidden worker used by the MariaDB-gated concurrency test (SequenceConcurrencyTest):
 * several of these run as concurrent OS processes so real InnoDB row locks — not an
 * in-process writer serialisation — are what prove gaplessness.
 *
 * Two mutually exclusive contexts:
 *  - `--tenant=<id>`   initialise real tenancy, then draw numbers from the tenant DB
 *                      (the production-faithful path).
 *  - `--database=<c>`  build a standalone connection from ZENON_STRESS_DB_* and make it
 *                      the default, no tenancy. This is the brief's sanctioned
 *                      simplification: full per-worker tenant init over the stress
 *                      connection was disproportionate, and numbering correctness is a
 *                      property of the row lock, not of tenancy. The migrated stress DB
 *                      carries just `companies` + `sequences`.
 *
 * Emits one generated value per line on stdout; the test collects and asserts them.
 */
class SequenceStressCommand extends Command
{
    protected $signature = 'zenon:sequence:stress {code} {--count=50} {--company=} {--tenant=} {--database=}';

    protected $description = 'Allocate N sequence numbers (internal concurrency-test worker)';

    protected $hidden = true;

    public function handle(): int
    {
        $database = $this->option('database');

        if (is_string($database) && $database !== '') {
            self::configureStressConnection($database);
            config(['database.default' => $database]);
        } else {
            $tenantId = $this->option('tenant');
            $tenant = is_string($tenantId) ? Tenant::find($tenantId) : null;

            if ($tenant === null) {
                $this->components->error(sprintf('Tenant [%s] not found.', is_string($tenantId) ? $tenantId : ''));

                return self::FAILURE;
            }

            tenancy()->initialize($tenant);
        }

        $generator = app(SequenceGenerator::class);

        $code = $this->argument('code');
        $count = (int) $this->option('count');
        $company = $this->option('company');
        $companyId = is_numeric($company) ? (int) $company : null;

        for ($i = 0; $i < $count; $i++) {
            $this->line($generator->next($code, $companyId));
        }

        return self::SUCCESS;
    }

    /**
     * Register a MySQL connection named $name from the ZENON_STRESS_DB_* environment.
     * Shared with the test so the parent process and every worker build an identical
     * connection from one source of truth. Reads via getenv() (not the env() helper): this
     * is an out-of-request test worker, and env values are passed explicitly into each
     * spawned worker's environment.
     */
    public static function configureStressConnection(string $name): void
    {
        config(["database.connections.{$name}" => [
            'driver' => 'mysql',
            'host' => self::stressEnv('ZENON_STRESS_DB_HOST', '127.0.0.1'),
            'port' => self::stressEnv('ZENON_STRESS_DB_PORT', '3306'),
            'database' => self::stressEnv('ZENON_STRESS_DB_DATABASE'),
            'username' => self::stressEnv('ZENON_STRESS_DB_USERNAME'),
            'password' => self::stressEnv('ZENON_STRESS_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]]);
    }

    private static function stressEnv(string $key, string $default = ''): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }
}
