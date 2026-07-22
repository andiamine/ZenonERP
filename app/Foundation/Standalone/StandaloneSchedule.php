<?php

namespace App\Foundation\Standalone;

use App\Foundation\DeploymentMode;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Cron-driven queue drain for standalone installs (CLAUDE.md §7: "cron-driven
 * scheduler + queue" — no supervisor/Horizon on Plesk/cPanel). Wired from
 * bootstrap/app.php's ->withSchedule() closure, gated on
 * {@see DeploymentMode::isStandalone()}.
 *
 * Design decisions, all load-bearing on commodity shared hosting:
 * - Foreground (no ->runInBackground()): background dispatch shells out via
 *   proc_open(), which is commonly present in a shared host's `disable_functions`
 *   ini blacklist. Running foreground is fine here — one drain runs sequentially
 *   inside the same `schedule:run` invocation the cron line already triggers every
 *   minute; it never blocks a second, unrelated scheduled task from being missed
 *   because withoutOverlapping (below) only fences overlapping RUNS OF THIS EVENT,
 *   not schedule:run itself.
 * - `--max-time=50`: bounds one drain to under the 60s cron cadence, so a busy queue
 *   still yields the process back to the next `schedule:run` tick instead of two
 *   overlapping drains fighting over the same jobs table.
 * - `--stop-when-empty --tries=3`: exits promptly when there is nothing to do
 *   (this is a foreground drain riding the cron tick, not a long-lived worker) and
 *   caps retries so one poison-pill job can't wedge the drain for its full
 *   `--max-time` budget every single minute.
 * - `withoutOverlapping(10)`: withoutOverlapping()'s DEFAULT $expiresAt is 1440
 *   MINUTES (a full day) — sized for long-running supervised workers, not this
 *   foreground drain. Without lowering it, a drain killed by an OOM (a real risk on
 *   memory-capped shared hosting) would leave a stale mutex that silences the queue
 *   for the rest of the day. 10 minutes is generous headroom over the ~1-minute
 *   cadence while still self-healing within the same shift.
 * - The overlap mutex resolves through the app's DEFAULT cache store, which is
 *   `database` in standalone (config/cache.php + the standalone .env template) —
 *   deliberately not pinned here via ->useCache(): standalone already runs without
 *   Redis, so the default store IS the safe store; hard-coding it would just be a
 *   second place to keep in sync with that assumption.
 *
 * Why this must be directly callable (not only reachable via bootstrap/app.php):
 * module-provider schedules (e.g. Audit's PruneActivityLogCommand) register via
 * nwidart's booted() hook, which resolves Schedule::class once during the app's
 * normal boot pass. bootstrap/app.php's ->withSchedule() callback is different — the
 * framework defers it behind Artisan::starting(), so it only actually runs once
 * something resolves the console Application (a real `php artisan ...` dispatch),
 * never on a plain HTTP-shaped boot, and a mid-test config('zenon.mode', ...) flip
 * cannot retroactively re-run either mechanism's boot-time pass. Tests therefore need
 * to invoke {@see self::register()} directly against the already-resolved Schedule
 * to assert its shape deterministically, independent of that plumbing.
 */
final class StandaloneSchedule
{
    public static function register(Schedule $schedule): void
    {
        $schedule->command('queue:work', [
            '--stop-when-empty',
            '--tries=3',
            '--max-time=50',
        ])
            ->everyMinute()
            ->withoutOverlapping(10);
    }
}
