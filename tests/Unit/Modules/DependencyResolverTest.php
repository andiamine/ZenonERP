<?php

use App\Foundation\Modules\DependencyResolver;
use App\Foundation\Modules\Exceptions\DependencyException;
use App\Foundation\Modules\ManifestData;

/**
 * @param  array<string, string>  $requires
 */
function resolverManifest(string $alias, string $version = '1.0.0', array $requires = []): ManifestData
{
    return new ManifestData(
        name: ucfirst($alias),
        alias: $alias,
        id: 'zenon/'.$alias,
        version: $version,
        core: false,
        requires: $requires,
        provides: [],
        hookEmits: [],
        permissions: [],
        frontendEntry: null,
        frontendRemote: null,
        platform: '^1.0',
        defaultEnabled: false,
        path: '/dev/null/'.$alias,
        providers: ['Modules\\'.ucfirst($alias).'\\Providers\\Provider'],
    );
}

it('resolves a linear chain dependencies-first', function () {
    $universe = [
        'a' => resolverManifest('a'),
        'b' => resolverManifest('b', requires: ['a' => '^1.0']),
        'c' => resolverManifest('c', requires: ['b' => '^1.0']),
    ];

    expect((new DependencyResolver)->resolveInstallOrder(['c'], $universe))->toBe(['a', 'b', 'c']);
});

it('resolves a diamond once, dependencies first', function () {
    $universe = [
        'base' => resolverManifest('base'),
        'left' => resolverManifest('left', requires: ['base' => '^1.0']),
        'right' => resolverManifest('right', requires: ['base' => '^1.0']),
        'top' => resolverManifest('top', requires: ['left' => '^1.0', 'right' => '^1.0']),
    ];

    $order = (new DependencyResolver)->resolveInstallOrder(['top'], $universe);

    expect($order)->toBe(['base', 'left', 'right', 'top']);
});

it('detects dependency cycles and names the path', function () {
    $universe = [
        'a' => resolverManifest('a', requires: ['b' => '^1.0']),
        'b' => resolverManifest('b', requires: ['a' => '^1.0']),
    ];

    expect(fn () => (new DependencyResolver)->resolveInstallOrder(['a'], $universe))
        ->toThrow(DependencyException::class, 'a -> b -> a');
});

it('reports missing dependencies with the requiring module', function () {
    $universe = [
        'a' => resolverManifest('a', requires: ['ghost' => '^1.0']),
    ];

    expect(fn () => (new DependencyResolver)->resolveInstallOrder(['a'], $universe))
        ->toThrow(DependencyException::class, 'Module [a] requires [ghost]');
});

it('reports semver violations with constraint and actual version', function () {
    $universe = [
        'old' => resolverManifest('old', version: '1.2.0'),
        'needy' => resolverManifest('needy', requires: ['old' => '^2.0']),
    ];

    expect(fn () => (new DependencyResolver)->resolveInstallOrder(['needy'], $universe))
        ->toThrow(DependencyException::class, 'requires [old ^2.0], but version 1.2.0');
});

it('lists direct dependents only', function () {
    $universe = [
        'base' => resolverManifest('base'),
        'mid' => resolverManifest('mid', requires: ['base' => '^1.0']),
        'top' => resolverManifest('top', requires: ['mid' => '^1.0']),
    ];

    expect((new DependencyResolver)->dependentsOf('base', $universe))->toBe(['mid'])
        ->and((new DependencyResolver)->dependentsOf('mid', $universe))->toBe(['top'])
        ->and((new DependencyResolver)->dependentsOf('top', $universe))->toBe([]);
});
