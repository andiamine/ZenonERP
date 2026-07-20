<?php

use App\Foundation\Modules\Models\InstalledModule;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
 * Phase 7 Task 8: zenon:module:install-zip — extracts a third-party addon zip into
 * modules/thirdparty and runs the NORMAL ModuleManager::install() flow (no SPA rebuild, no
 * Node). Zips are built programmatically against a temp thirdparty_path + a scan.paths
 * override so the committed Demo addon is never part of this scenario; `composer
 * dump-autoload` is always Process::fake()d — the zip-shipped provider class becomes
 * resolvable in-process purely via AddonZipInstaller's live-loader patch, which is exactly
 * the mechanism under test.
 */

beforeEach(function () {
    $this->tmpTarget = storage_path('framework/testing/install-zip-target-'.uniqid());
    $this->zipDir = storage_path('framework/testing/install-zip-source-'.uniqid());
    File::ensureDirectoryExists($this->tmpTarget);
    File::ensureDirectoryExists($this->zipDir);

    config([
        'zenon.thirdparty_path' => $this->tmpTarget,
        // Replaces the real modules/thirdparty/* glob (so committed Demo is never
        // discovered here) while keeping the standing fixture path other suites rely on.
        'modules.scan.paths' => [
            $this->tmpTarget.'/*',
            base_path('tests/Fixtures/modules/*'),
        ],
    ]);

    app(ModuleRegistry::class)->flush();

    Process::fake();
});

afterEach(function () {
    File::deleteDirectory($this->tmpTarget);
    File::deleteDirectory($this->zipDir);
});

/**
 * Builds a minimal valid "Zipdemo" addon zip: module.json + composer.json (psr-4 autoload)
 * + a provider class extending the Foundation base — the provider is only resolvable via
 * the live-loader patch under test, never via the static composer.json autoload-dev map.
 *
 * @param  array<string, string>  $extraEntries  extra zip entry name => contents
 */
function buildZipdemoZip(
    string $zipPath,
    ?string $wrapper = null,
    string $platform = '^1.0',
    array $extraEntries = [],
    bool $includeManifest = true,
    ?string $rawManifest = null,
    ?string $remote = null,
): void {
    $prefix = $wrapper !== null ? $wrapper.'/' : '';

    $manifest = $rawManifest ?? json_encode([
        'name' => 'Zipdemo',
        'alias' => 'zipdemo',
        'providers' => ['Modules\\Zipdemo\\Providers\\ZipdemoServiceProvider'],
        'zenon' => [
            'id' => 'acme/zipdemo',
            'version' => '1.0.0',
            'core' => false,
            'requires' => [],
            'provides' => [],
            'hooks' => ['emits' => []],
            'permissions' => [],
            'frontend' => ['entry' => null, 'remote' => $remote],
            'platform' => $platform,
            'defaultEnabled' => false,
        ],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

    $composerJson = json_encode([
        'name' => 'acme/zipdemo',
        'type' => 'zenon-module',
        'autoload' => ['psr-4' => ['Modules\\Zipdemo\\' => 'app/']],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

    $provider = <<<'PHP'
        <?php

        namespace Modules\Zipdemo\Providers;

        use App\Foundation\Modules\ModuleServiceProvider;

        class ZipdemoServiceProvider extends ModuleServiceProvider
        {
            protected string $name = 'Zipdemo';

            protected string $nameLower = 'zipdemo';
        }

        PHP;

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    if ($includeManifest) {
        $zip->addFromString($prefix.'module.json', $manifest);
    }

    $zip->addFromString($prefix.'composer.json', $composerJson);
    $zip->addFromString($prefix.'app/Providers/ZipdemoServiceProvider.php', $provider);

    foreach ($extraEntries as $entryName => $contents) {
        $zip->addFromString($prefix.$entryName, $contents);
    }

    $zip->close();
}

/** Asserts a directory (that must still exist) contains no entries at all. */
function assertNoResidueIn(string $dir): void
{
    expect(is_dir($dir))->toBeTrue();
    expect(array_diff(scandir($dir) ?: [], ['.', '..']))->toBe([]);
}

it('installs a root-level zip: extracts files, creates the central module row, runs dump-autoload', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('Installed [zipdemo]')
        ->assertSuccessful();

    expect(is_dir($this->tmpTarget.'/Zipdemo'))->toBeTrue();
    expect(is_file($this->tmpTarget.'/Zipdemo/module.json'))->toBeTrue();
    expect(is_file($this->tmpTarget.'/Zipdemo/composer.json'))->toBeTrue();
    expect(is_file($this->tmpTarget.'/Zipdemo/app/Providers/ZipdemoServiceProvider.php'))->toBeTrue();

    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeTrue();

    Process::assertRan('composer dump-autoload');
});

it('resolves a path given relative to base_path()', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath);

    $relative = ltrim(str_replace(base_path(), '', $zipPath), '/\\');

    $this->artisan('zenon:module:install-zip', ['path' => $relative])->assertSuccessful();

    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeTrue();
});

it('installs a single-top-level-wrapper zip (what Compress-Archive/Finder produce)', function () {
    $zipPath = $this->zipDir.'/wrapped.zip';
    buildZipdemoZip($zipPath, wrapper: 'SomeWrapperDir');

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])->assertSuccessful();

    expect(is_dir($this->tmpTarget.'/Zipdemo'))->toBeTrue();
    expect(is_file($this->tmpTarget.'/Zipdemo/module.json'))->toBeTrue();
    // The wrapper name itself must never leak into the extracted tree.
    expect(is_dir($this->tmpTarget.'/SomeWrapperDir'))->toBeFalse();

    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeTrue();
});

it('refuses when the target folder already exists, leaving pre-existing content untouched', function () {
    File::ensureDirectoryExists($this->tmpTarget.'/Zipdemo');
    File::put($this->tmpTarget.'/Zipdemo/sentinel.txt', 'pre-existing');

    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(File::get($this->tmpTarget.'/Zipdemo/sentinel.txt'))->toBe('pre-existing');
    expect(is_file($this->tmpTarget.'/Zipdemo/module.json'))->toBeFalse();
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeFalse();
});

it('refuses an incompatible platform constraint, mentioning both versions, leaving no residue', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, platform: '^9.0');

    // A single substring check spanning both tokens: Laravel's expectsOutputToContain()
    // consumes one matching console write per call, so two overlapping checks against the
    // SAME single-line message would race each other for that one write.
    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain(sprintf(
            'platform [^9.0], but this platform is version [%s]',
            config('zenon.platform_version'),
        ))
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeFalse();
});

it('refuses a remote-declaring addon whose platform the SPA loader cannot evaluate, leaving no residue', function () {
    // '~1.2' is valid composer/semver but outside the loader grammar
    // (`*` | `^MAJOR[.MINOR[.PATCH]]` | bare `MAJOR[.MINOR[.PATCH]]`) — an addon shipping a
    // remote frontend with this platform string would install cleanly and then be
    // permanently refused at mount, undiagnosable. Preflight must catch it up front.
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, platform: '~1.2', remote: 'dist/remoteEntry.js');

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('addons with a remote frontend must declare platform')
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeFalse();
});

it('installs a remote-declaring addon whose platform the SPA loader can evaluate (^1.0)', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, platform: '^1.0', remote: 'dist/remoteEntry.js');

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('Installed [zipdemo]')
        ->assertSuccessful();

    expect(is_dir($this->tmpTarget.'/Zipdemo'))->toBeTrue();
    expect(is_file($this->tmpTarget.'/Zipdemo/module.json'))->toBeTrue();
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeTrue();

    Process::assertRan('composer dump-autoload');
});

it('passes preflight for a backend-only addon (no remote frontend) using a platform constraint outside the loader grammar', function () {
    // No frontend.remote → full composer/semver freedom; the loader grammar restriction
    // from the previous two scenarios must NOT apply here. Override platform_version so
    // the (unrelated, still-enforced) Semver::satisfies() check also succeeds — this
    // proves the whole preflight passes, not just the grammar check.
    config(['zenon.platform_version' => '1.5.0']);

    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, platform: '~1.2', remote: null);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('Installed [zipdemo]')
        ->assertSuccessful();

    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeTrue();
});

it('refuses a zip-slip entry escaping via ".." segments, leaving no residue', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, extraEntries: ['../evil.txt' => 'pwned']);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('..')
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeFalse();
});

it('refuses a backslash zip entry, leaving no residue', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, extraEntries: ['evil\\payload.txt' => 'pwned']);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('backslash')
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeFalse();
});

it('refuses an absolute-path zip entry, leaving no residue', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, extraEntries: ['/etc/evil.txt' => 'pwned']);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('absolute path')
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeFalse();
});

it('refuses a drive-letter-prefixed zip entry, leaving no residue', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, extraEntries: ['C:/Windows/evil.txt' => 'pwned']);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('drive letter')
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeFalse();
});

it('refuses a module name that fails the target-folder whitelist regex, leaving no residue', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, rawManifest: json_encode([
        'name' => '../../Evil',
        'alias' => 'evil',
        'providers' => [],
        'zenon' => [
            'id' => 'acme/evil',
            'version' => '1.0.0',
            'core' => false,
            'requires' => [],
            'provides' => [],
            'hooks' => ['emits' => []],
            'permissions' => [],
            'frontend' => ['entry' => null, 'remote' => null],
            'platform' => '^1.0',
            'defaultEnabled' => false,
        ],
    ], JSON_THROW_ON_ERROR));

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('must match')
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
    expect(InstalledModule::query()->where('alias', 'evil')->exists())->toBeFalse();
});

it('skips symlink entries during extraction instead of materializing them', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath);

    // Patch a symlink entry into the already-built zip: unix external-attributes mode
    // 0120777 is what ZipArchive::getExternalAttributesIndex() reports for a symlink.
    $zip = new ZipArchive;
    $zip->open($zipPath);
    $zip->addFromString('evil-link.txt', 'would-be symlink target content');
    $zip->setExternalAttributesName('evil-link.txt', ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->close();

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])->assertSuccessful();

    expect(file_exists($this->tmpTarget.'/Zipdemo/evil-link.txt'))->toBeFalse();
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeTrue();
});

it('refuses an invalid JSON manifest, leaving no residue', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, rawManifest: '{ this is not valid json');

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('not valid JSON')
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeFalse();
});

it('refuses a zip with no module.json anywhere, leaving no residue', function () {
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, includeManifest: false);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('module.json')
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeFalse();
});

it('refuses an unreadable zip file', function () {
    $notAZip = $this->zipDir.'/not-a-zip.zip';
    File::put($notAZip, 'this is definitely not a zip archive');

    $this->artisan('zenon:module:install-zip', ['path' => $notAZip])
        ->expectsOutputToContain('not a readable zip')
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
});

it('refuses an alias that is already discovered on the platform, leaving no residue', function () {
    // "dummy" is discovered via the kept tests/Fixtures/modules/* scan path.
    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath, rawManifest: json_encode([
        'name' => 'Zipdemo',
        'alias' => 'dummy',
        'providers' => ['Modules\\Zipdemo\\Providers\\ZipdemoServiceProvider'],
        'zenon' => [
            'id' => 'acme/dummy',
            'version' => '1.0.0',
            'core' => false,
            'requires' => [],
            'provides' => [],
            'hooks' => ['emits' => []],
            'permissions' => [],
            'frontend' => ['entry' => null, 'remote' => null],
            'platform' => '^1.0',
            'defaultEnabled' => false,
        ],
    ], JSON_THROW_ON_ERROR));

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('already discovered')
        ->assertFailed();

    assertNoResidueIn($this->tmpTarget);
});

it('rolls back the extracted target directory when composer dump-autoload fails', function () {
    // A closure fake REPLACES the handler map outright (the array form MERGES onto
    // beforeEach's wildcard '*' success handler, which — being registered first — would
    // keep winning the match ahead of a more specific keyed handler added afterwards).
    Process::fake(fn () => Process::result(exitCode: 1, errorOutput: 'boom'));

    $zipPath = $this->zipDir.'/zipdemo.zip';
    buildZipdemoZip($zipPath);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('composer dump-autoload failed')
        ->assertFailed();

    expect(is_dir($this->tmpTarget.'/Zipdemo'))->toBeFalse();
    expect(InstalledModule::query()->where('alias', 'zipdemo')->exists())->toBeFalse();

    Process::assertRan('composer dump-autoload');
});
