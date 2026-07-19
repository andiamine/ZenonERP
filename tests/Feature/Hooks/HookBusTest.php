<?php

use App\Foundation\Hooks\ActionVetoedException;
use App\Foundation\Hooks\HookBus;
use Modules\Dummy\Contracts\Hooks\DummyItemsApiResponse;
use Tests\Fixtures\Hooks\AppendAfterVeto;
use Tests\Fixtures\Hooks\AppendAlpha;
use Tests\Fixtures\Hooks\AppendBeta;
use Tests\Fixtures\Hooks\AppendGamma;
use Tests\Fixtures\Hooks\ConfirmProbePayload;
use Tests\Fixtures\Hooks\OrderProbePayload;
use Tests\Fixtures\Hooks\VetoIfForbidden;

it('runs filters in ascending priority order with ties in registration order', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    $bus = app(HookBus::class);
    $bus->register(OrderProbePayload::class, AppendGamma::class, 'dummy', 200);
    $bus->register(OrderProbePayload::class, AppendAlpha::class, 'dummy', 50);
    $bus->register(OrderProbePayload::class, AppendBeta::class, 'dummy', 200); // ties with gamma, registered after

    $in = new OrderProbePayload;
    $out = $acme->run(fn () => $bus->filter($in));

    expect($out)->toBe($in)
        ->and($out->log)->toBe(['alpha', 'gamma', 'beta']);
});

it('is a no-op for payload classes with no registered filters', function () {
    $payload = new OrderProbePayload;

    expect(app(HookBus::class)->filter($payload))->toBe($payload)
        ->and($payload->log)->toBe([]);
});

it('container-resolves filters registered through the Extend API', function () {
    installModule('dummy');
    installModule('dummydep');
    $acme = createTenant('acme');
    enableModule('dummydep', $acme); // auto-enables dummy (manifest requires)

    // AddDummyComputedField has a constructor dependency and was registered by
    // DummyDepServiceProvider::boot() via $this->extend() — the full sanctioned path.
    $payload = $acme->run(fn () => app(HookBus::class)->filter(new DummyItemsApiResponse));

    expect($payload->extra['computed_by'])->toBe('dummydep')
        ->and($payload->extra['app_name'])->toBe(config('app.name'));
});

it('skips filters from modules not enabled for the current tenant', function () {
    installModule('dummy');
    installModule('dummydep');
    $acme = createTenant('acme');
    enableModule('dummy', $acme); // dummydep stays disabled

    $bus = app(HookBus::class);
    $bus->register(OrderProbePayload::class, AppendAlpha::class, 'dummydep', 50);
    $bus->register(OrderProbePayload::class, AppendBeta::class, 'dummy', 100);

    $payload = $acme->run(fn () => $bus->filter(new OrderProbePayload));

    expect($payload->log)->toBe(['beta']); // alpha (dummydep) skipped despite its lower priority
});

it('gates one registration set differently per tenant', function () {
    installModule('dummy');
    installModule('dummydep');
    $acme = createTenant('acme');
    $beta = createTenant('beta');
    enableModule('dummydep', $acme);
    enableModule('dummy', $beta);

    $bus = app(HookBus::class);

    $forAcme = $acme->run(fn () => $bus->filter(new DummyItemsApiResponse));
    $forBeta = $beta->run(fn () => $bus->filter(new DummyItemsApiResponse));

    expect($forAcme->extra)->toHaveKey('computed_by')
        ->and($forBeta->extra)->toBe([]);
});

it('skips every module filter outside tenant context', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    $bus = app(HookBus::class);
    $bus->register(OrderProbePayload::class, AppendAlpha::class, 'dummy');

    $payload = $bus->filter(new OrderProbePayload); // central context — no tenant initialized

    expect($payload->log)->toBe([]);
});

it('propagates a veto with reason and code, skipping later filters', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    $bus = app(HookBus::class);
    $bus->register(ConfirmProbePayload::class, VetoIfForbidden::class, 'dummy', 10);
    $bus->register(ConfirmProbePayload::class, AppendAfterVeto::class, 'dummy', 100);

    $payload = new ConfirmProbePayload('forbidden');

    try {
        $acme->run(fn () => $bus->filter($payload));
        $this->fail('Expected ActionVetoedException was not thrown.');
    } catch (ActionVetoedException $e) {
        expect($e->getMessage())->toBe('Name is forbidden.')
            ->and($e->vetoCode)->toBe('probe.forbidden');
    }

    expect($payload->log)->toBe([]); // veto threw first; AppendAfterVeto never ran
});

it('runs the full chain when no filter vetoes', function () {
    installModule('dummy');
    $acme = createTenant('acme');
    enableModule('dummy', $acme);

    $bus = app(HookBus::class);
    $bus->register(ConfirmProbePayload::class, VetoIfForbidden::class, 'dummy', 10);
    $bus->register(ConfirmProbePayload::class, AppendAfterVeto::class, 'dummy', 100);

    $payload = new ConfirmProbePayload('widget');
    $acme->run(fn () => $bus->filter($payload));

    expect($payload->log)->toBe(['veto-check', 'after']);
});
