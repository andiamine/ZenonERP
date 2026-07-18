<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\Audit\AuditTestHelpers;

/**
 * @return array{0: Activity, 1: Activity, 2: Activity} oldest, middle, newest
 */
function seedThreeActivities(): array
{
    $oldest = Activity::query()->create([
        'log_name' => 'audit', 'description' => 'created', 'event' => 'created',
        'subject_type' => 'widget', 'subject_id' => 1,
        'properties' => ['attributes' => ['name' => 'A']],
        'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10),
    ]);
    $middle = Activity::query()->create([
        'log_name' => 'audit', 'description' => 'updated', 'event' => 'updated',
        'subject_type' => 'widget', 'subject_id' => 1,
        'properties' => ['attributes' => ['name' => 'B'], 'old' => ['name' => 'A']],
        'created_at' => now()->subDays(1), 'updated_at' => now()->subDays(1),
    ]);
    $newest = Activity::query()->create([
        'log_name' => 'audit', 'description' => 'created', 'event' => 'created',
        'subject_type' => 'gizmo', 'subject_id' => 2,
        'properties' => ['attributes' => ['x' => 1]],
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$oldest, $middle, $newest];
}

it('lists activities sorted newest-first by default', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(function () use ($user) {
        $user->givePermissionTo('audit.activities.view');
        seedThreeActivities();
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/audit/activities', [], $cookie)
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.subject_type', 'gizmo')   // newest
        ->assertJsonPath('data.2.subject_type', 'widget');  // oldest, event=created
});

it('filters by event, subject_type and subject_id', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(function () use ($user) {
        $user->givePermissionTo('audit.activities.view');
        seedThreeActivities();
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/audit/activities?filter[event]=updated', [], $cookie)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', 'updated');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/audit/activities?filter[subject_type]=widget', [], $cookie)
        ->assertOk()
        ->assertJsonCount(2, 'data');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/audit/activities?filter[subject_id]=2', [], $cookie)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject_type', 'gizmo');
});

it('filters by a created_at from/to range', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(function () use ($user) {
        $user->givePermissionTo('audit.activities.view');
        seedThreeActivities();
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    $from = now()->subDays(2)->toDateTimeString();

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/audit/activities?filter[from]='.urlencode($from), [], $cookie)
        ->assertOk()
        ->assertJsonCount(2, 'data'); // middle + newest, oldest (10 days ago) excluded

    $to = now()->subDays(2)->toDateTimeString();

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/audit/activities?filter[to]='.urlencode($to), [], $cookie)
        ->assertOk()
        ->assertJsonCount(1, 'data'); // only the oldest (10 days ago)
});

it('reports properties/causer shape and flips 403 to 200 once the view permission is granted', function () {
    $tenant = AuditTestHelpers::bootAuditTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(function () use ($user) {
        Activity::query()->create([
            'log_name' => 'audit', 'description' => 'created', 'event' => 'created',
            'causer_type' => User::class, 'causer_id' => $user->id,
            'properties' => ['attributes' => ['name' => 'A']],
        ]);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    assertErrorEnvelope(
        statefulJson('get', 'acme.zenonerp.test', '/api/v1/audit/activities', [], $cookie),
        403,
        'forbidden',
    );

    $tenant->run(fn () => $user->givePermissionTo('audit.activities.view'));

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/audit/activities', [], $cookie)
        ->assertOk()
        ->assertJsonPath('data.0.causer.id', $user->id)
        ->assertJsonPath('data.0.causer.name', $user->name)
        ->assertJsonPath('data.0.properties.attributes.name', 'A');
});

it('is behaviorally invisible for a tenant without audit enabled', function () {
    $beta = createTenant('beta');
    installModule('audit'); // installed platform-wide, but NOT enabled for beta

    assertModuleInvisibleFor($beta, '/api/v1/audit/activities');
});
