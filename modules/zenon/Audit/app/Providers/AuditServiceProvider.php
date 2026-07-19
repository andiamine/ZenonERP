<?php

namespace Modules\Audit\Providers;

use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Modules\Audit\Console\PruneActivityLogCommand;
use Modules\Core\Contracts\Settings\SettingDefinition;
use Modules\Core\Contracts\Settings\SettingsRegistrar;

class AuditServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Audit';

    protected string $nameLower = 'audit';

    public function boot(): void
    {
        parent::boot();

        // nwidart's registerCommands() only registers the (empty) $commands property;
        // module console classes are not auto-discovered, so register explicitly
        // (mirrors Sequence's SequenceServiceProvider).
        $this->commands([PruneActivityLogCommand::class]);

        $this->registerAuditSettings();
    }

    /**
     * Dogfoods cross-module typed settings (CLAUDE.md §9.1): Contracts-only import from
     * Core, registered every boot into the request-scoped SettingsRegistry. module:
     * 'audit' gates the READ/WRITE path (SettingsRepository) so this definition — and
     * any stored value — is invisible for a tenant where zenon/audit isn't enabled, even
     * though registration itself is platform-wide like routes (CLAUDE.md §6/§13 risk #1;
     * controller-directed fix, see task report).
     *
     * Boot resilience (CLAUDE.md §5, §13 risk #2): Core's provider is normally what binds
     * SettingsRegistrar, but provider boot order is not something this module controls,
     * and a drifted modules_statuses.json can leave Audit "active" while Core's provider
     * never registers at all (real incident: central `modules` table had core installed,
     * but the derived statuses artifact lacked it). An unguarded make() here throws
     * BindingResolutionException during provider boot, which bricks EVERY entry point —
     * including `zenon:module:doctor`, the tool that's supposed to diagnose and repair
     * exactly this kind of drift. So: skip and log instead of throwing. The app must stay
     * bootable so doctor can run.
     */
    protected function registerAuditSettings(): void
    {
        if (! $this->app->bound(SettingsRegistrar::class)) {
            Log::warning('audit.settings_registrar_unavailable', [
                'reason' => 'core module provider not registered - platform state is broken; run zenon:module:doctor',
            ]);

            return;
        }

        $this->app->make(SettingsRegistrar::class)->register(
            new SettingDefinition('audit.retention_days', 'int', 365, 'Audit log retention (days)', module: 'audit'),
        );
    }

    /**
     * Detected by nwidart's registerCommandSchedules() via method_exists() and invoked
     * once the app has booted (CLAUDE.md §5 scheduled-command registration pattern).
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command(PruneActivityLogCommand::class)->daily();
    }
}
