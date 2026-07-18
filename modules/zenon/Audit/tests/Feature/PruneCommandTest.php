<?php

use App\Foundation\Modules\ModuleManager;
use Illuminate\Support\Carbon;
use Modules\Core\Contracts\Settings\SettingsRepository;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\Audit\AuditTestHelpers;

afterEach(function () {
    Carbon::setTestNow();
});

function seedActivityAt(Carbon $createdAt, string $tag = 'x'): Activity
{
    return Activity::query()->create([
        'log_name' => 'audit', 'description' => $tag, 'event' => 'created',
        'properties' => ['attributes' => []],
        'created_at' => $createdAt, 'updated_at' => $createdAt,
    ]);
}

it('deletes rows older than the resolved retention window and keeps newer ones', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();

    $tenant->run(function () {
        seedActivityAt(now()->subDays(400), 'old'); // older than the 365-day default → pruned
        seedActivityAt(now()->subDays(10), 'new');  // newer → kept
    });

    $this->artisan('zenon:audit:prune')->assertSuccessful();

    $tenant->run(function () {
        expect(Activity::query()->count())->toBe(1)
            ->and(Activity::query()->value('description'))->toBe('new'); // the OLD row is the one pruned
    });
});

it('lets --days override win over the per-tenant setting and the config default', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();

    $tenant->run(function () {
        app(SettingsRepository::class)->set('audit.retention_days', 365); // would keep everything below
        seedActivityAt(now()->subDays(40), 'old');
        seedActivityAt(now()->subDays(5), 'new');
    });

    $this->artisan('zenon:audit:prune', ['--days' => 30])->assertSuccessful();

    $tenant->run(function () {
        expect(Activity::query()->count())->toBe(1)
            ->and(Activity::query()->value('description'))->toBe('new');
    });
});

it('resolves the per-tenant audit.retention_days setting over the config default', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();

    $tenant->run(function () {
        app(SettingsRepository::class)->set('audit.retention_days', 10);
        seedActivityAt(now()->subDays(20), 'old'); // older than 10 → pruned
        seedActivityAt(now()->subDays(5), 'new');  // newer than 10 → kept
    });

    $this->artisan('zenon:audit:prune')->assertSuccessful();

    $tenant->run(function () {
        expect(Activity::query()->count())->toBe(1)
            ->and(Activity::query()->value('description'))->toBe('new');
    });
});

it('skips tenants where audit is not enabled', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();
    $tenant->run(fn () => seedActivityAt(now()->subDays(400))); // would qualify for pruning if not skipped

    app(ModuleManager::class)->disableForTenant('audit', $tenant);

    $this->artisan('zenon:audit:prune')->assertSuccessful();

    // Disable ≠ purge: the table and its row survive untouched because the tenant was
    // skipped entirely (never even had tenancy initialized for it).
    $tenant->run(function () {
        expect(Activity::query()->count())->toBe(1);
    });
});

it('limits pruning to one tenant via --tenant', function () {
    $acme = AuditTestHelpers::bootAuditTenant('acme');
    $beta = AuditTestHelpers::bootAuditTenant('beta');

    $acme->run(fn () => seedActivityAt(now()->subDays(400)));
    $beta->run(fn () => seedActivityAt(now()->subDays(400)));

    $this->artisan('zenon:audit:prune', ['--tenant' => 'acme'])->assertSuccessful();

    $acme->run(fn () => expect(Activity::query()->count())->toBe(0));
    $beta->run(fn () => expect(Activity::query()->count())->toBe(1)); // untouched
});
