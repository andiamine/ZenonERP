<?php

use App\Foundation\Modules\ManifestValidator;
use App\Foundation\Modules\ModuleRegistry;
use App\Foundation\Modules\ModuleServiceProvider;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\Module;

/*
 * CI-fatal conventions for every discovered module (seeds the §2 boundary suite;
 * the full cross-module Contracts-only arch rule activates with Phase 5's real modules).
 */

it('every discovered module has a valid manifest', function () {
    /** @var Module $module */
    foreach (app(RepositoryInterface::class)->all() as $module) {
        app(ManifestValidator::class)->validate($module->json()->getAttributes(), $module->getPath());
    }

    expect(app(ModuleRegistry::class)->discovered())->not->toBeEmpty();
});

it('every module provider extends the Foundation base and matches its manifest alias', function () {
    foreach (app(ModuleRegistry::class)->discovered() as $alias => $manifest) {
        foreach ($manifest->providers as $providerClass) {
            expect(is_a($providerClass, ModuleServiceProvider::class, true))
                ->toBeTrue("Provider [$providerClass] of module [$alias] must extend the Foundation ModuleServiceProvider.");

            $provider = new $providerClass(app());
            $method = new ReflectionMethod($provider, 'alias');

            expect($method->invoke($provider))->toBe(
                $alias,
                "Provider [$providerClass] alias() must equal manifest alias [$alias].",
            );
        }
    }
});
