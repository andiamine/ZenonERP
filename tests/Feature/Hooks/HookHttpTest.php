<?php

use Modules\DummyDep\Listeners\RecordDummyConfirmation;

beforeEach(fn () => RecordDummyConfirmation::reset());

it('appends computed fields only for tenants with the extension enabled', function () {
    installModule('dummy');
    installModule('dummydep');
    $acme = createTenant('acme');
    $beta = createTenant('beta');
    enableModule('dummydep', $acme); // auto-enables dummy
    enableModule('dummy', $beta);    // dummydep stays disabled

    $this->getJson('http://acme.zenonerp.test/api/v1/dummy/items')
        ->assertOk()
        ->assertJsonPath('extra.computed_by', 'dummydep');

    $this->getJson('http://beta.zenonerp.test/api/v1/dummy/items')
        ->assertOk()
        ->assertJsonMissingPath('extra.computed_by');
});

it('returns the 422 action_vetoed envelope when an extension vetoes the action', function () {
    installModule('dummy');
    installModule('dummydep');
    $acme = createTenant('acme');
    enableModule('dummydep', $acme);

    $response = $this->postJson('http://acme.zenonerp.test/api/v1/dummy/items/confirm', ['name' => 'forbidden']);

    assertErrorEnvelope($response, 422, 'action_vetoed')
        ->assertJsonPath('error.message', 'Dummy item name is forbidden.')
        ->assertJsonPath('error.code', 'dummydep.forbidden_name');

    expect(RecordDummyConfirmation::$confirmed)->toBe([]); // vetoed → the event never fired
});

it('confirms and notifies the cross-module listener where enabled', function () {
    installModule('dummy');
    installModule('dummydep');
    $acme = createTenant('acme');
    enableModule('dummydep', $acme);

    $this->postJson('http://acme.zenonerp.test/api/v1/dummy/items/confirm', ['name' => 'widget'])
        ->assertOk()
        ->assertJsonPath('data.confirmed', 'widget');

    expect(RecordDummyConfirmation::$confirmed)->toBe(['widget']);
});

it('confirms with zero hook and listener execution where the extension is disabled', function () {
    installModule('dummy');
    installModule('dummydep');
    $beta = createTenant('beta');
    enableModule('dummy', $beta);

    // Same input a dummydep tenant gets vetoed on — here the veto filter is skipped.
    $this->postJson('http://beta.zenonerp.test/api/v1/dummy/items/confirm', ['name' => 'forbidden'])
        ->assertOk()
        ->assertJsonPath('data.confirmed', 'forbidden');

    expect(RecordDummyConfirmation::$confirmed)->toBe([]); // TenantGatedListener no-oped
});

it('keeps module routes invisible for tenants that never enabled it', function () {
    installModule('dummy');
    $ghost = createTenant('ghost');

    assertModuleInvisibleFor($ghost, '/api/v1/dummy/items');
    $this->postJson('http://ghost.zenonerp.test/api/v1/dummy/items/confirm', ['name' => 'x'])->assertNotFound();
});
