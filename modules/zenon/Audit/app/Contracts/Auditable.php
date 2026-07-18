<?php

namespace Modules\Audit\Contracts;

use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Opt-in change auditing for a model (CLAUDE.md §9.2, the MIX binding — the Odoo
 * mail.thread lesson). One line ("use Auditable;") wires the model into spatie's
 * event-driven activity logger with ZenonERP defaults: fillable attributes only,
 * dirty-only diffs on update, no empty logs, secrets excluded, log name "audit".
 *
 * v4.12 hook adaptation (per the task brief — read BEFORE writing this file:
 * vendor/spatie/laravel-activitylog/src/Traits/LogsActivity.php):
 *   - The event-gating hook in 4.12 is `protected function shouldLogEvent(string
 *     $eventName): bool` — it is NOT abstract; LogsActivity already ships a concrete
 *     implementation (enableLoggingModelsEvents flag + ActivityLogStatus::disabled() +
 *     "is this a restore?" + "did only ignored attributes change?"). We must not
 *     re-implement any of that — trait method aliasing renames the original to
 *     `spatieShouldLogEvent()` so our override can delegate to it after the tenant gate.
 *   - `bootLogsActivity()` (also part of LogsActivity) calls `$model->shouldLogEvent()`
 *     by late static/dynamic dispatch, so our override below — declared directly in this
 *     trait, not inside LogsActivity — wins automatically (a using trait's own method
 *     always takes precedence over a method pulled in from a trait it uses; no
 *     `insteadof` is needed because this isn't a two-trait conflict).
 *
 * Disabled-module invisibility (CLAUDE.md §13 risk #1, §11): the WRITE path itself is
 * gated here, not just the HTTP surface — a consumer model keeps `use Auditable;`
 * unconditionally regardless of tenant state; nothing is written to activity_log for a
 * tenant where zenon/audit is disabled (proven by AuditableTest).
 *
 * Consumers may override getActivitylogOptions() in their own class — a class-declared
 * method always beats a trait method, so this is the sanctioned "customize the options"
 * escape hatch without touching this trait.
 *
 * @mixin Model
 */
trait Auditable
{
    use LogsActivity {
        shouldLogEvent as spatieShouldLogEvent;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->logExcept(['password', 'remember_token'])
            ->useLogName('audit');
    }

    protected function shouldLogEvent(string $eventName): bool
    {
        return app(ModuleRegistry::class)->isEnabledForCurrentTenant('audit')
            && $this->spatieShouldLogEvent($eventName);
    }
}
