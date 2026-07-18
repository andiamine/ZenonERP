<?php

namespace Modules\Audit\Providers;

use App\Foundation\Modules\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
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

        // Dogfoods cross-module typed settings (CLAUDE.md §9.1): Contracts-only import
        // from Core, registered every boot into the request-scoped SettingsRegistry.
        // module: 'audit' gates the READ/WRITE path (SettingsRepository) so this
        // definition — and any stored value — is invisible for a tenant where zenon/audit
        // isn't enabled, even though registration itself is platform-wide like routes
        // (CLAUDE.md §6/§13 risk #1; controller-directed fix, see task report).
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
