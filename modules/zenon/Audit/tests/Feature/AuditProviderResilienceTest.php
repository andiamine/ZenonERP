<?php

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use Modules\Audit\Providers\AuditServiceProvider;
use Modules\Core\Contracts\Settings\SettingsRegistrar;
use Modules\Core\Services\SettingsRegistry;

/**
 * Boot-resilience regression (CLAUDE.md §5, §13 risk #2): a drifted
 * modules_statuses.json can leave Audit "active" while Core's provider never
 * registers, so nothing ever binds SettingsRegistrar. The standing test suite
 * always writes ALL modules active in the statuses file, so it never
 * exercised that drift — this test builds the missing-binding scenario
 * directly rather than through the fixture module set.
 *
 * A fresh, empty container (never the real app()) stands in for "Core's
 * provider didn't register" — Audit's registerAuditSettings() is exercised
 * directly against it via reflection so the guard is verified in isolation
 * from provider boot order and container caching.
 */
it('logs a warning and does not register settings when SettingsRegistrar is unbound', function () {
    Log::spy();

    $container = new Container;
    $provider = new AuditServiceProvider($container);

    (new ReflectionMethod($provider, 'registerAuditSettings'))->invoke($provider);

    expect($container->bound(SettingsRegistrar::class))->toBeFalse();

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('audit.settings_registrar_unavailable', [
            'reason' => 'core module provider not registered - platform state is broken; run zenon:module:doctor',
        ]);
});

it('registers audit.retention_days when SettingsRegistrar is bound', function () {
    Log::spy();

    $container = new Container;
    $registry = new SettingsRegistry;
    $container->instance(SettingsRegistrar::class, $registry);

    $provider = new AuditServiceProvider($container);

    (new ReflectionMethod($provider, 'registerAuditSettings'))->invoke($provider);

    expect($registry->definitions())->toHaveKey('audit.retention_days');

    $definition = $registry->definitions()['audit.retention_days'];

    expect($definition->type)->toBe('int')
        ->and($definition->default)->toBe(365)
        ->and($definition->module)->toBe('audit');

    Log::shouldNotHaveReceived('warning');
});
