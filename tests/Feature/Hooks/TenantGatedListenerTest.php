<?php

use Modules\Dummy\Contracts\Events\DummyItemConfirmed;
use Modules\DummyDep\Listeners\RecordDummyConfirmation;

beforeEach(fn () => RecordDummyConfirmation::reset());

it('delegates where the module is enabled and no-ops where it is not', function () {
    installModule('dummy');
    installModule('dummydep');
    $acme = createTenant('acme');
    $beta = createTenant('beta');
    enableModule('dummydep', $acme);
    enableModule('dummy', $beta);

    // The listener was registered by DummyDepServiceProvider::boot() via
    // $this->extend()->listen(...) — i.e. wrapped in TenantGatedListener.
    $acme->run(fn () => DummyItemConfirmed::dispatch('from-acme'));
    $beta->run(fn () => DummyItemConfirmed::dispatch('from-beta'));

    expect(RecordDummyConfirmation::$confirmed)->toBe(['from-acme']);
});

it('no-ops outside tenant context', function () {
    installModule('dummy');
    installModule('dummydep');
    $acme = createTenant('acme');
    enableModule('dummydep', $acme);

    DummyItemConfirmed::dispatch('central'); // central context — no tenant initialized

    expect(RecordDummyConfirmation::$confirmed)->toBe([]);
});
