<?php

use Illuminate\Support\Facades\File;

/*
 * Phase 7 Task 8: zenon:module:package — packages a modules/thirdparty/{name} folder into
 * a distributable zip consumable by zenon:module:install-zip. thirdparty_path points at a
 * temp fixture module built on disk for most scenarios; the real committed Demo module
 * (default thirdparty_path) is exercised separately, guarded by a skip-if-missing so this
 * suite never couples to Task 6/7's build ordering.
 */

beforeEach(function () {
    $this->thirdpartyPath = storage_path('framework/testing/package-source-'.uniqid());
    $this->outDir = storage_path('framework/testing/package-out-'.uniqid());
    File::ensureDirectoryExists($this->thirdpartyPath);

    config(['zenon.thirdparty_path' => $this->thirdpartyPath]);
});

afterEach(function () {
    File::deleteDirectory($this->thirdpartyPath);
    File::deleteDirectory($this->outDir);
});

/**
 * Builds a minimal "Acme" fixture addon on disk, with node_modules/.git/tests planted so
 * exclusion can be asserted, plus a dist/remoteEntry.js for the frontend.remote declaration.
 *
 * @param  array<string, mixed>  $zenonOverrides  shallow-merged into the zenon block
 */
function buildFixtureAddon(string $root, array $zenonOverrides = []): void
{
    File::ensureDirectoryExists($root.'/dist');
    File::ensureDirectoryExists($root.'/node_modules/somelib');
    File::ensureDirectoryExists($root.'/.git');
    File::ensureDirectoryExists($root.'/tests');
    File::ensureDirectoryExists($root.'/app');

    File::put($root.'/node_modules/somelib/index.js', 'excluded');
    File::put($root.'/.git/HEAD', 'excluded');
    File::put($root.'/tests/SomeTest.php', 'excluded');
    File::put($root.'/app/SomeClass.php', '<?php // included');
    File::put($root.'/dist/remoteEntry.js', 'export default {};');

    $zenon = array_merge([
        'id' => 'acme/acme',
        'version' => '1.0.0',
        'core' => false,
        'requires' => [],
        'provides' => [],
        'hooks' => ['emits' => []],
        'permissions' => [],
        'frontend' => ['entry' => null, 'remote' => 'dist/remoteEntry.js'],
        'platform' => '^1.0',
        'defaultEnabled' => false,
    ], $zenonOverrides);

    $manifest = [
        'name' => 'Acme',
        'alias' => 'acme',
        'providers' => [],
        'zenon' => $zenon,
    ];

    File::put($root.'/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}

/** @return list<string> */
function zipEntryNames(string $zipPath): array
{
    $zip = new ZipArchive;
    $zip->open($zipPath);

    $names = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = $zip->getNameIndex($i);
    }

    $zip->close();

    return $names;
}

it('packages a folder into a correctly named zip, contents at root, excluding node_modules/.git/tests', function () {
    buildFixtureAddon($this->thirdpartyPath.'/Acme');

    $this->artisan('zenon:module:package', ['name' => 'Acme', '--out' => $this->outDir])
        ->expectsOutputToContain('Packaged')
        ->assertSuccessful();

    $zipPath = $this->outDir.'/acme-acme-1.0.0.zip';
    expect(is_file($zipPath))->toBeTrue();

    $names = zipEntryNames($zipPath);

    expect($names)->toContain('module.json')
        ->toContain('dist/remoteEntry.js')
        ->toContain('app/SomeClass.php');

    foreach ($names as $name) {
        expect($name)->not->toContain('node_modules')
            ->not->toContain('.git/')
            ->not->toContain('tests/');
    }
});

it('refuses to package when frontend.remote is declared but the built file is missing', function () {
    buildFixtureAddon($this->thirdpartyPath.'/Acme');
    File::delete($this->thirdpartyPath.'/Acme/dist/remoteEntry.js');

    $this->artisan('zenon:module:package', ['name' => 'Acme', '--out' => $this->outDir])
        ->expectsOutputToContain('remoteEntry.js')
        ->assertFailed();

    expect(is_file($this->outDir.'/acme-acme-1.0.0.zip'))->toBeFalse();
});

it('refuses when the source module does not exist', function () {
    $this->artisan('zenon:module:package', ['name' => 'Ghost', '--out' => $this->outDir])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();

    expect(is_dir($this->outDir))->toBeFalse();
});

it('defaults the output directory to storage/app/packages when --out is omitted', function () {
    buildFixtureAddon($this->thirdpartyPath.'/Acme', ['frontend' => ['entry' => null, 'remote' => null]]);

    $defaultOut = storage_path('app/packages');
    File::deleteDirectory($defaultOut);

    try {
        $this->artisan('zenon:module:package', ['name' => 'Acme'])->assertSuccessful();

        expect(is_file($defaultOut.'/acme-acme-1.0.0.zip'))->toBeTrue();
    } finally {
        File::deleteDirectory($defaultOut);
    }
});

it('packages the real committed Demo module when its dist is already built', function () {
    $demoDist = base_path('modules/thirdparty/Demo/dist/remoteEntry.js');

    if (! is_file($demoDist)) {
        $this->markTestSkipped('Demo dist/remoteEntry.js is not built in this environment (Task 6/7 build ordering).');
    }

    config(['zenon.thirdparty_path' => base_path('modules/thirdparty')]);

    $this->artisan('zenon:module:package', ['name' => 'Demo', '--out' => $this->outDir])
        ->assertSuccessful();

    $zipPath = $this->outDir.'/acme-demo-1.0.0.zip';
    expect(is_file($zipPath))->toBeTrue();

    $names = zipEntryNames($zipPath);
    expect($names)->toContain('module.json')
        ->toContain('dist/remoteEntry.js');

    foreach ($names as $name) {
        expect($name)->not->toContain('node_modules');
    }
});
