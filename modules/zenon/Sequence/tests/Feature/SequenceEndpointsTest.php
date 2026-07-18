<?php

use Modules\Sequence\Contracts\SequenceDefinition;
use Modules\Sequence\Models\Sequence;
use Modules\Sequence\Services\SequenceRegistry;

it('lists materialised sequence rows with a computed preview', function () {
    $tenant = bootSequenceTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(function () use ($user) {
        $user->givePermissionTo('sequence.sequences.view');
        Sequence::query()->create(['code' => 'so', 'mask' => 'SO-{seq:4}', 'next_number' => 7]);
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/sequence/sequences', [], $cookie)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'so')
        ->assertJsonPath('data.0.next_number', 7)
        ->assertJsonPath('data.0.preview', 'SO-0007'); // next value, un-consumed
});

it('lists all registered definitions with a materialized flag', function () {
    $tenant = bootSequenceTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $tenant->run(fn () => $user->givePermissionTo('sequence.sequences.view'));

    // Mimic consumer modules defining sequences in their provider boot(): afterResolving
    // re-applies on every (scoped) resolution, so the definition survives the request
    // lifecycle regardless of scoped-instance flushing.
    app()->afterResolving(SequenceRegistry::class, function (SequenceRegistry $registry) {
        $registry->define(new SequenceDefinition('inv', 'INV-{seq:5}', 'year', false, true, 'Invoices'));
        $registry->define(new SequenceDefinition('so', 'SO-{seq:4}'));
    });

    // 'so' has a row (materialized); 'inv' does not.
    $tenant->run(fn () => Sequence::query()->create(['code' => 'so', 'mask' => 'SO-{seq:4}']));

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    $data = statefulJson('get', 'acme.zenonerp.test', '/api/v1/sequence/definitions', [], $cookie)
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data');

    $byCode = collect($data)->keyBy('code');

    expect($byCode['inv'])->toMatchArray([
        'code' => 'inv', 'mask' => 'INV-{seq:5}', 'reset_period' => 'year',
        'per_company' => false, 'gapless' => true, 'label' => 'Invoices', 'materialized' => false,
    ])->and($byCode['so']['materialized'])->toBeTrue();
});

it('updates a sequence mask and reset_period via PATCH', function () {
    $tenant = bootSequenceTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $sequenceId = $tenant->run(function () use ($user) {
        $user->givePermissionTo('sequence.sequences.update');

        return Sequence::query()->create(['code' => 'so', 'mask' => 'SO-{seq:4}'])->id;
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    statefulJson('patch', 'acme.zenonerp.test', "/api/v1/sequence/sequences/{$sequenceId}", [
        'mask' => '{year}-{seq:5}', 'reset_period' => 'year',
    ], $cookie)
        ->assertOk()
        ->assertJsonPath('data.mask', '{year}-{seq:5}')
        ->assertJsonPath('data.reset_period', 'year');

    $tenant->run(fn () => expect(Sequence::query()->find($sequenceId)->mask)->toBe('{year}-{seq:5}'));
});

it('rejects a mask without a {seq} token with a 422 envelope', function () {
    $tenant = bootSequenceTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);
    $sequenceId = $tenant->run(function () use ($user) {
        $user->givePermissionTo('sequence.sequences.update');

        return Sequence::query()->create(['code' => 'so', 'mask' => 'SO-{seq:4}'])->id;
    });

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    $response = statefulJson('patch', 'acme.zenonerp.test', "/api/v1/sequence/sequences/{$sequenceId}", [
        'mask' => 'NO-COUNTER-HERE',
    ], $cookie);

    assertErrorEnvelope($response, 422, 'validation_error');
    expect(array_key_exists('mask', $response->json('error.errors')))->toBeTrue();
});

it('flips 403 to 200 for GET /sequences once the view permission is granted', function () {
    $tenant = bootSequenceTenant();
    $user = tenantUser($tenant, ['email' => 'user@acme.test']);

    [, $cookie] = loginOn('acme.zenonerp.test', 'user@acme.test');

    assertErrorEnvelope(
        statefulJson('get', 'acme.zenonerp.test', '/api/v1/sequence/sequences', [], $cookie),
        403,
        'forbidden',
    );

    $tenant->run(fn () => $user->givePermissionTo('sequence.sequences.view'));

    statefulJson('get', 'acme.zenonerp.test', '/api/v1/sequence/sequences', [], $cookie)
        ->assertOk();
});

it('is behaviorally invisible for a tenant without sequence enabled', function () {
    $beta = createTenant('beta');
    installModule('sequence'); // installed platform-wide, but NOT enabled for beta

    assertModuleInvisibleFor($beta, '/api/v1/sequence/sequences');
    assertModuleInvisibleFor($beta, '/api/v1/sequence/definitions');
});
