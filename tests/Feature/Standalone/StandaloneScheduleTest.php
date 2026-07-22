<?php

use App\Foundation\Standalone\StandaloneSchedule;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Phase 8 Task 11: standalone cron/queue wiring.
 *
 * bootstrap/app.php's ->withSchedule() callback is wrapped by Laravel's
 * ApplicationBuilder in Artisan::starting() — it only actually runs once something
 * resolves the console Application (a real "php artisan ..." dispatch), never on a
 * plain HTTP-shaped boot. Tests that need to observe the REAL bootstrap wiring
 * (the saas gate, composition with Audit's module-contributed schedule) force that
 * resolution with a throwaway `$this->artisan('list')` call first. The direct-call
 * test doesn't depend on that plumbing at all — see StandaloneSchedule's docblock for
 * why direct callability matters.
 */
function standaloneScheduleQueueWorkEvents(Schedule $schedule): array
{
    return array_values(array_filter(
        $schedule->events(),
        fn ($event) => str_contains((string) $event->command, 'queue:work'),
    ));
}

it('registers a foreground queue:work drain with a safe cadence and overlap guard', function () {
    $schedule = app(Schedule::class);

    expect(standaloneScheduleQueueWorkEvents($schedule))->toBeEmpty();

    StandaloneSchedule::register($schedule);

    $events = standaloneScheduleQueueWorkEvents($schedule);
    expect($events)->toHaveCount(1);

    $event = $events[0];

    expect($event->command)->toContain('queue:work')
        ->and($event->command)->toContain('--stop-when-empty')
        ->and($event->command)->toContain('--tries=3')
        ->and($event->command)->toContain('--max-time=50')
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(10);
});

it('does not register queue:work on a default (saas) boot', function () {
    $this->artisan('list')->assertSuccessful();

    expect(standaloneScheduleQueueWorkEvents(app(Schedule::class)))->toBeEmpty();
});

it('composes with Audit module-contributed schedule when standalone mode is booted', function () {
    config(['zenon.mode' => 'standalone']);

    $this->artisan('list')->assertSuccessful();

    $schedule = app(Schedule::class);

    expect(standaloneScheduleQueueWorkEvents($schedule))->toHaveCount(1);

    $auditEvents = array_values(array_filter(
        $schedule->events(),
        fn ($event) => str_contains((string) $event->command, 'zenon:audit:prune'),
    ));

    expect($auditEvents)->toHaveCount(1);
});
