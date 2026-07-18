<?php

namespace Modules\Audit\Console;

use App\Foundation\Modules\ModuleRegistry;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\Settings\SettingsReader;
use Spatie\Activitylog\Models\Activity;

/**
 * Central-context command (CLAUDE.md §9.2 retention policy). Scheduled daily by
 * AuditServiceProvider::configureSchedules(); also runnable ad hoc/via cron on standalone
 * installs. Retention resolution order per tenant: --days flag > the tenant's own
 * `audit.retention_days` setting (Core's typed settings, §9.1) > the package config
 * default (config/activitylog.php).
 *
 * Tenants where zenon/audit is not enabled are skipped entirely — checked via
 * ModuleRegistry BEFORE tenancy is initialized (TenantModule is a central-DB table), so a
 * disabled tenant's DB is never even connected to (CLAUDE.md §13 risk #1 extended to
 * background jobs, not just HTTP).
 */
class PruneActivityLogCommand extends Command
{
    protected $signature = 'zenon:audit:prune {--tenant= : Limit to one tenant id} {--days= : Override the resolved retention window in days}';

    protected $description = 'Delete activity_log rows older than the resolved retention window, per tenant.';

    public function handle(ModuleRegistry $registry): int
    {
        $tenants = $this->resolveTenants();

        if ($tenants === null) {
            return self::FAILURE;
        }

        $overrideDays = $this->overrideDays();

        foreach ($tenants as $tenant) {
            if (! $registry->isEnabledFor('audit', $tenant)) {
                continue;
            }

            tenancy()->initialize($tenant);

            try {
                $days = $overrideDays
                    ?? $this->intOrNull(app(SettingsReader::class)->get('audit.retention_days'))
                    ?? (int) config('activitylog.delete_records_older_than_days', 365);

                $cutoff = Carbon::now()->subDays($days);

                $deleted = Activity::query()->where('created_at', '<', $cutoff)->delete();

                $this->components->info(sprintf(
                    'Tenant [%s]: pruned %d activity row(s) older than %d day(s).',
                    (string) $tenant->getTenantKey(), $deleted, $days,
                ));
            } finally {
                tenancy()->end();
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return iterable<Tenant>|null null = an explicit --tenant id was given but not found
     */
    private function resolveTenants(): ?iterable
    {
        $tenantId = $this->option('tenant');

        if (! is_string($tenantId) || $tenantId === '') {
            return Tenant::query()->cursor();
        }

        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            $this->components->error(sprintf('Tenant [%s] not found.', $tenantId));

            return null;
        }

        return [$tenant];
    }

    private function overrideDays(): ?int
    {
        $days = $this->option('days');

        return is_numeric($days) ? (int) $days : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
