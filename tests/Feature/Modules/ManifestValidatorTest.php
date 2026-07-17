<?php

use App\Foundation\Modules\Exceptions\InvalidManifestException;
use App\Foundation\Modules\ManifestValidator;

/**
 * @return array<string, mixed>
 */
function validManifest(): array
{
    return [
        'name' => 'Dummy',
        'alias' => 'dummy',
        'providers' => ['Modules\\Dummy\\Providers\\DummyServiceProvider'],
        'zenon' => [
            'id' => 'zenon/dummy',
            'version' => '1.2.3',
            'core' => false,
            'requires' => ['other' => '^2.0'],
            'provides' => ['dummy.items'],
            'hooks' => ['emits' => []],
            'permissions' => ['dummy.items.view'],
            'frontend' => ['entry' => 'resources/js/index.ts', 'remote' => null],
            'platform' => '^1.0',
            'defaultEnabled' => true,
        ],
    ];
}

/**
 * @return array<string, list<string>>
 */
function manifestErrorsFor(array $manifest): array
{
    try {
        app(ManifestValidator::class)->validate($manifest, '/tmp/module');
    } catch (InvalidManifestException $e) {
        return $e->errors();
    }

    test()->fail('Expected InvalidManifestException was not thrown.');
}

it('accepts a fully valid manifest and returns typed data', function () {
    $data = app(ManifestValidator::class)->validate(validManifest(), '/tmp/module');

    expect($data->name)->toBe('Dummy')
        ->and($data->alias)->toBe('dummy')
        ->and($data->id)->toBe('zenon/dummy')
        ->and($data->version)->toBe('1.2.3')
        ->and($data->core)->toBeFalse()
        ->and($data->requires)->toBe(['other' => '^2.0'])
        ->and($data->provides)->toBe(['dummy.items'])
        ->and($data->permissions)->toBe(['dummy.items.view'])
        ->and($data->frontendEntry)->toBe('resources/js/index.ts')
        ->and($data->frontendRemote)->toBeNull()
        ->and($data->platform)->toBe('^1.0')
        ->and($data->defaultEnabled)->toBeTrue()
        ->and($data->path)->toBe('/tmp/module')
        ->and($data->providers)->toBe(['Modules\\Dummy\\Providers\\DummyServiceProvider']);
});

it('rejects a manifest without a zenon block', function () {
    $manifest = validManifest();
    unset($manifest['zenon']);

    expect(manifestErrorsFor($manifest))->toHaveKey('zenon');
});

it('rejects a malformed id', function () {
    $manifest = validManifest();
    $manifest['zenon']['id'] = 'NotAVendorId';

    expect(manifestErrorsFor($manifest))->toHaveKey('zenon.id');
});

it('rejects an id that does not end in the alias', function () {
    $manifest = validManifest();
    $manifest['zenon']['id'] = 'zenon/other';

    expect(manifestErrorsFor($manifest))->toHaveKey('zenon.id');
});

it('rejects a non-semver version', function () {
    $manifest = validManifest();
    $manifest['zenon']['version'] = 'not-a-version';

    expect(manifestErrorsFor($manifest))->toHaveKey('zenon.version');
});

it('rejects an invalid requires constraint', function () {
    $manifest = validManifest();
    $manifest['zenon']['requires'] = ['other' => 'garbage constraint ^^'];

    expect(manifestErrorsFor($manifest))->toHaveKey('zenon.requires.other');
});

it('rejects unknown keys inside the zenon block', function () {
    $manifest = validManifest();
    $manifest['zenon']['surprise'] = true;

    expect(manifestErrorsFor($manifest))->toHaveKey('zenon.surprise');
});

it('rejects a provider class that does not exist', function () {
    $manifest = validManifest();
    $manifest['providers'] = ['Modules\\Nope\\Providers\\NopeServiceProvider'];

    expect(manifestErrorsFor($manifest))->toHaveKey('providers.0');
});

it('rejects an invalid platform constraint', function () {
    $manifest = validManifest();
    $manifest['zenon']['platform'] = '!!nope';

    expect(manifestErrorsFor($manifest))->toHaveKey('zenon.platform');
});
