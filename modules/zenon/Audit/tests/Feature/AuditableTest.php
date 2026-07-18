<?php

use App\Foundation\Modules\ModuleManager;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\Audit\AuditTestHelpers;
use Tests\Fixtures\Audit\AuditProbe;

it('logs a created activity with fillable attributes and excludes password-ish fields', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();

    $tenant->run(function () {
        AuditTestHelpers::createAuditProbesTable();

        $probe = AuditProbe::create(['name' => 'Alpha', 'password' => 'secret']);

        expect(Activity::query()->count())->toBe(1);

        $activity = Activity::query()->firstOrFail();
        $attributes = $activity->properties->get('attributes');

        expect($activity->log_name)->toBe('audit')
            ->and($activity->event)->toBe('created')
            ->and($activity->subject_type)->toBe(AuditProbe::class)
            ->and($activity->subject_id)->toBe($probe->id)
            ->and($attributes)->toMatchArray(['name' => 'Alpha'])
            ->and(array_key_exists('password', $attributes))->toBeFalse()
            ->and($activity->properties->has('old'))->toBeFalse(); // no diff key on create
    });
});

it('logs an updated activity with old/attributes diffs limited to the dirty field only', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();

    $tenant->run(function () {
        AuditTestHelpers::createAuditProbesTable();

        $probe = AuditProbe::create(['name' => 'Alpha', 'password' => 'secret']);
        $probe->update(['name' => 'Beta']); // password untouched → must not appear in the diff

        $activity = Activity::query()->where('event', 'updated')->firstOrFail();

        expect($activity->properties->get('attributes'))->toBe(['name' => 'Beta'])
            ->and($activity->properties->get('old'))->toBe(['name' => 'Alpha']);
    });
});

it('writes ZERO activity rows for the same operations once audit is disabled for the tenant (§13 risk #1)', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();

    $tenant->run(function () {
        AuditTestHelpers::createAuditProbesTable();
    });

    // Disable only (not purge): activity_log + audit_probes tables stay, only the
    // enablement flag flips — the trait's write-gate must react to THIS, live.
    app(ModuleManager::class)->disableForTenant('audit', $tenant);

    $tenant->run(function () {
        $probe = AuditProbe::create(['name' => 'Alpha']);
        $probe->update(['name' => 'Beta']);
        $probe->delete();

        expect(Activity::query()->count())->toBe(0);
    });
});
