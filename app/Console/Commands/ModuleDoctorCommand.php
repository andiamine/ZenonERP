<?php

namespace App\Console\Commands;

use App\Foundation\Modules\ManifestData;
use App\Foundation\Modules\Models\InstalledModule;
use App\Foundation\Modules\Models\TenantModule;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Modules\TenantModuleMigrator;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Nwidart\Modules\Contracts\ActivatorInterface;

/**
 * Reports drift between the intended module state and reality (CLAUDE.md §5/§13 risk #2).
 * Basic checks in Phase 2; re-queue/repair tooling hardens in Phase 9.
 */
class ModuleDoctorCommand extends Command
{
    protected $signature = 'zenon:module:doctor {--module= : Limit checks to one module alias}';

    protected $description = 'Diagnose module state drift: version drift, pending tenant migrations, statuses-file desync';

    public function handle(
        ModuleRegistry $registry,
        TenantModuleMigrator $migrator,
        ActivatorInterface $activator,
    ): int {
        $only = $this->option('module');
        $issues = 0;

        $installedRows = InstalledModule::query()
            ->when(is_string($only), fn ($q) => $q->where('alias', $only))
            ->get();

        // (a) central `modules` version vs on-disk manifest version.
        foreach ($installedRows as $row) {
            $manifest = $registry->discovered()[$row->alias] ?? null;

            if ($manifest === null) {
                $this->components->error(sprintf('[%s] installed but no module found on disk.', $row->alias));
                $issues++;

                continue;
            }

            if ($manifest->version !== $row->version) {
                $this->components->warn(sprintf(
                    '[%s] code version %s differs from installed version %s — run zenon:module:upgrade.',
                    $row->alias, $manifest->version, $row->version,
                ));
                $issues++;
            }

            // (d) nwidart statuses file desync.
            if (! $activator->hasStatus($row->name, true)) {
                $this->components->error(sprintf(
                    '[%s] is installed but inactive in the nwidart statuses file (module name [%s]).',
                    $row->alias, $row->name,
                ));
                $issues++;
            }
        }

        // (b) + (c) per enabled tenant row: migrated_version drift + pending tenant migrations.
        $tenantRows = TenantModule::query()
            ->where('enabled', true)
            ->when(is_string($only), fn ($q) => $q->where('module', $only))
            ->get()
            ->groupBy('tenant_id');

        foreach ($tenantRows as $tenantId => $rows) {
            $tenant = Tenant::find((string) $tenantId);

            if ($tenant === null) {
                continue;
            }

            /** @var array<string, list<string>> $pendingByModule */
            $pendingByModule = [];

            tenancy()->initialize($tenant);

            try {
                foreach ($rows as $row) {
                    $manifest = $registry->discovered()[$row->module] ?? null;

                    if ($manifest instanceof ManifestData) {
                        $pendingByModule[$row->module] = $migrator->pending($manifest);
                    }
                }
            } finally {
                tenancy()->end();
            }

            foreach ($rows as $row) {
                $installedVersion = $installedRows->firstWhere('alias', $row->module)?->version;

                if ($installedVersion !== null && $row->migrated_version !== $installedVersion) {
                    $this->components->warn(sprintf(
                        'Tenant [%s]: [%s] is at %s, platform is at %s.',
                        (string) $tenantId, $row->module, $row->migrated_version ?? 'unknown', $installedVersion,
                    ));
                    $issues++;
                }

                foreach ($pendingByModule[$row->module] ?? [] as $pending) {
                    $this->components->warn(sprintf(
                        'Tenant [%s]: [%s] has pending tenant migration [%s].',
                        (string) $tenantId, $row->module, $pending,
                    ));
                    $issues++;
                }
            }
        }

        if ($issues === 0) {
            $this->components->info('All modules healthy — no drift detected.');

            return self::SUCCESS;
        }

        $this->components->error(sprintf('%d issue(s) found.', $issues));

        return self::FAILURE;
    }
}
