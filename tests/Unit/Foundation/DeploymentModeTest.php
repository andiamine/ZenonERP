<?php

use App\Foundation\DeploymentMode;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

it('defaults to Saas when zenon.mode is the out-of-the-box config value', function () {
    expect(DeploymentMode::current())->toBe(DeploymentMode::Saas)
        ->and(DeploymentMode::isStandalone())->toBeFalse();
});

it('resolves Standalone when zenon.mode is "standalone"', function () {
    Config::set('zenon.mode', 'standalone');

    expect(DeploymentMode::current())->toBe(DeploymentMode::Standalone)
        ->and(DeploymentMode::isStandalone())->toBeTrue();
});

it('fails closed to Saas on an unrecognized mode string', function () {
    Config::set('zenon.mode', 'garbage');

    expect(DeploymentMode::current())->toBe(DeploymentMode::Saas)
        ->and(DeploymentMode::isStandalone())->toBeFalse();
});

it('fails closed to Saas when zenon.mode is null', function () {
    Config::set('zenon.mode', null);

    expect(DeploymentMode::current())->toBe(DeploymentMode::Saas)
        ->and(DeploymentMode::isStandalone())->toBeFalse();
});

it('fails closed to Saas when zenon.mode is an empty string', function () {
    Config::set('zenon.mode', '');

    expect(DeploymentMode::current())->toBe(DeploymentMode::Saas);
});
