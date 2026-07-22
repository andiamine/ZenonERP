<?php

use App\Foundation\Release\ReleasePackager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
 * Phase 8 Task 12: zenon:release:package — the pipeline that builds the zip installing
 * ZenonERP on a bare Apache+MySQL host with no shell, no Node, no Composer. Modeled after
 * ModulePackageCommandTest (fixture builders, zipEntryNames pattern — renamed here to avoid
 * colliding with that file's top-level Pest functions in the same process) and
 * ModuleInstallZipCommandTest (Process::fake() closure patterns).
 *
 * Fixture tree lives under `zenon.release.source_root` (the test seam) planted with
 * decoys mirroring every exclusion rule in ReleasePackager: dev vendor is never planted
 * (nothing exercises it — vendor/ only ever exists in STAGING, produced by the fake
 * composer install below), but every other allowlist edge case is: root dotfiles/
 * dev-only files, node_modules, tests/, packages/, modules_statuses.json, the storage/
 * and bootstrap/cache skeleton-only rules, database.sqlite + zenon_tenant_* files, and
 * modules/thirdparty/Demo (which must NEVER ship in a platform release zip — Demo ships
 * separately via `zenon:module:package Demo`).
 *
 * Sharp edge (documented, not worked around): the FIRST preflight step —
 * `zenon:frontend:generate --check` — runs for REAL against the actual repo's committed
 * module-registry.ts (never faked/weakened). Naively, that command run inside THIS suite
 * (APP_ENV=testing) reports a false "stale" failure: config/modules.php appends
 * tests/Fixtures/modules/* to modules.scan.paths under APP_ENV=testing, and several of
 * those fixtures (Dummy, DummyCore) declare a frontend.entry, inflating discovered()
 * beyond what the committed registry was generated against — confirmed empirically
 * (`APP_ENV=testing php artisan zenon:frontend:generate --check` fails outside Pest too,
 * while the same command under the normal env passes). ReleasePackager's
 * assertRegistryFresh() resolves this WITHOUT touching the real command: it strips any
 * tests/-rooted scan path from modules.scan.paths for the duration of that one nested
 * Artisan::call, restoring it immediately after — a legitimate production hardening
 * (a release must reflect the real product's module set regardless of what scan paths
 * happen to be configured), not a test-only shortcut. No test here exercises the "stale
 * registry" failure branch itself (that would require deliberately committing a stale
 * module-registry.ts, which is undesirable); every other preflight failure (missing
 * build manifest, missing phar, dirty git tree) IS covered below.
 */

beforeEach(function () {
    $this->sourceRoot = storage_path('framework/testing/release-source-'.uniqid());
    $this->outDir = storage_path('framework/testing/release-out-'.uniqid());
    $this->pharDir = storage_path('framework/testing/release-phar-'.uniqid());
    $this->pharPath = $this->pharDir.'/composer.phar';

    File::ensureDirectoryExists($this->sourceRoot);
    File::ensureDirectoryExists($this->pharDir);
    File::put($this->pharPath, 'dummy phar contents — never actually executed, Process is always faked below');

    buildReleaseSourceFixture($this->sourceRoot);

    config([
        'zenon.release.source_root' => $this->sourceRoot,
        'zenon.release.out_dir' => $this->outDir,
        'zenon.composer.phar_path' => $this->pharPath,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->sourceRoot);
    File::deleteDirectory($this->outDir);
    File::deleteDirectory($this->pharDir);
});

/**
 * Plants a minimal source tree covering every root file/dir the allowlist mentions, PLUS
 * a decoy for every exclusion rule ReleasePackager enforces. See the file docblock above
 * for the full inventory.
 */
function buildReleaseSourceFixture(string $root): void
{
    File::ensureDirectoryExists($root);

    // --- root files: allowlisted, must survive ---
    File::put($root.'/artisan', "#!/usr/bin/env php\n<?php // fixture artisan\n");
    File::put($root.'/composer.json', json_encode(['name' => 'fixture/app'], JSON_PRETTY_PRINT));
    File::put($root.'/composer.lock', json_encode(['packages' => []], JSON_PRETTY_PRINT));
    File::put($root.'/.env.example', "APP_NAME=Fixture\n");
    File::put($root.'/.env.standalone', "APP_NAME=Fixture\nZENON_MODE=standalone\n");

    // --- root decoys: never allowlisted, must never appear in any zip ---
    File::put($root.'/.env', "APP_KEY=super-secret-real-dev-env\n");
    File::put($root.'/CLAUDE.md', "# internal project notes\n");
    File::put($root.'/modules_statuses.json', '{"core": true}');

    File::ensureDirectoryExists($root.'/node_modules');
    File::put($root.'/node_modules/x.js', 'module.exports = {};');

    File::ensureDirectoryExists($root.'/tests');
    File::put($root.'/tests/Something.php', '<?php // decoy top-level test');

    File::ensureDirectoryExists($root.'/packages');
    File::put($root.'/packages/pkg.json', '{}');

    // --- public/ ---
    File::ensureDirectoryExists($root.'/public/build/assets');
    File::put($root.'/public/build/manifest.json', '{}');
    File::put($root.'/public/.htaccess', "# apache rewrite rules\n");
    File::put($root.'/public/hot', 'http://[::1]:5173');

    // --- storage/ (skeleton-only rule: only .gitignore survives, anywhere under storage/) ---
    File::ensureDirectoryExists($root.'/storage/logs');
    File::put($root.'/storage/logs/.gitignore', "*\n!.gitignore\n");
    File::put($root.'/storage/logs/laravel.log', "[2026-07-22] production.ERROR: decoy log line\n");

    // --- storage/framework/installed.lock: the installer's completion marker — must
    // survive an update untouched (it is never even a candidate for the zip: the
    // skeleton-only rule already strips every non-.gitignore file under storage/**, this
    // decoy locks that guarantee down explicitly for the one file whose presence in a
    // release zip would be actively dangerous, not just noise) ---
    File::ensureDirectoryExists($root.'/storage/framework');
    File::put($root.'/storage/framework/installed.lock', "installed-at=2026-07-22T00:00:00Z\n");

    // --- storage/app/zenon-tmp: the packager's OWN staging root now that staging is
    // derived from source_root (see ReleasePackager::package()) — a stale leftover
    // directory tree from a crashed prior run (planted here) AND the staging directory
    // the pipeline itself creates for the CURRENTLY-RUNNING pack (self-inclusion) must
    // both be pruned from the walk entirely, never merely filtered file-by-file ---
    File::ensureDirectoryExists($root.'/storage/app/zenon-tmp/release-stale/foo');
    File::put($root.'/storage/app/zenon-tmp/release-stale/foo/.gitignore', "*\n!.gitignore\n");

    // --- storage/app/releases: the out_dir CONFIG DEFAULT — these tests point --out
    // elsewhere, but the relative path is walked whenever zenon.release.out_dir is left
    // at its default (storage_path('app/releases')) in production, so old zips' sibling
    // .gitignore files must never leak in either ---
    File::ensureDirectoryExists($root.'/storage/app/releases');
    File::put($root.'/storage/app/releases/.gitignore', "*\n!.gitignore\n");

    // --- bootstrap/ (bootstrap/cache/ emptied except .gitignore; bootstrap/*.php kept) ---
    File::ensureDirectoryExists($root.'/bootstrap/cache');
    File::put($root.'/bootstrap/cache/.gitignore', "*\n!.gitignore\n");
    File::put($root.'/bootstrap/cache/services.php', '<?php // decoy compiled services cache');
    File::put($root.'/bootstrap/app.php', '<?php // fixture bootstrap/app.php');
    File::put($root.'/bootstrap/providers.php', '<?php return [];');

    // --- database/ (database.sqlite + zenon_tenant_* excluded; everything else kept) ---
    File::ensureDirectoryExists($root.'/database/migrations');
    File::put($root.'/database/.gitignore', "*.sqlite\n");
    File::put($root.'/database/migrations/2024_01_01_000000_create_x_table.php', '<?php // fixture migration');
    File::put($root.'/database/database.sqlite', 'binary-ish sqlite decoy');
    File::put($root.'/database/zenon_tenant_default.sqlite', 'binary-ish tenant sqlite decoy');
    // Regression fixture: a crashed test run's leftover scratch file (e.g. the standing
    // installer suite's database/phase8_*.sqlite) — gitignored (*.sqlite* in
    // database/.gitignore), so it's invisible to the git-clean preflight and must be
    // caught by isExcludedDatabaseFile()'s broadened *.sqlite* matching, not by name.
    File::put($root.'/database/leftover_test.sqlite', 'binary-ish leftover sqlite decoy');

    // --- config/, app/: minimal, just to prove plain inclusion ---
    File::ensureDirectoryExists($root.'/config');
    File::put($root.'/config/app.php', '<?php return [];');

    File::ensureDirectoryExists($root.'/app/Foundation');
    File::put($root.'/app/Foundation/Placeholder.php', '<?php // fixture app file');

    // --- modules/zenon/Core (its own tests/ decoy, excluded by dir-name at any depth) ---
    File::ensureDirectoryExists($root.'/modules/zenon/Core/tests');
    File::put($root.'/modules/zenon/Core/module.json', '{"name":"Core"}');
    File::put($root.'/modules/zenon/Core/tests/T.php', '<?php // decoy module test');

    // --- modules/thirdparty/Demo: must be wholly excluded from EVERY release zip ---
    File::ensureDirectoryExists($root.'/modules/thirdparty/Demo/dist');
    File::ensureDirectoryExists($root.'/modules/thirdparty/Demo/node_modules');
    File::put($root.'/modules/thirdparty/Demo/module.json', '{"name":"Demo"}');
    File::put($root.'/modules/thirdparty/Demo/dist/remoteEntry.js', 'export default {};');
    File::put($root.'/modules/thirdparty/Demo/node_modules/lib.js', 'module.exports = {};');

    // --- modules/thirdparty/.gitkeep: survives in FULL zips only ---
    File::put($root.'/modules/thirdparty/.gitkeep', '');
}

/** @return list<string> */
function releaseZipEntryNames(string $zipPath): array
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

function releaseZipEntryContents(string $zipPath, string $name): string
{
    $zip = new ZipArchive;
    $zip->open($zipPath);

    $contents = (string) $zip->getFromName($name);

    $zip->close();

    return $contents;
}

/**
 * Fakes both Process invocations the pipeline makes, discriminated by command shape:
 * `git status --porcelain` (array, first element literally 'git') returns $porcelain as
 * its output; anything else (the composer install invocation — ComposerRunner always
 * emits an ARRAY command here since the fixture phar exists on disk) gets its SIDE EFFECT
 * applied — writing vendor/autoload.php into the staging dir, derived from the recorded
 * process's `path` — then reports success. This is what lets ReleasePackager's hard
 * verify-after-install step pass under a fake.
 */
function fakeReleaseProcesses(string $porcelain = ''): void
{
    Process::fake(function ($process) use ($porcelain) {
        $command = $process->command;

        if (is_array($command) && ($command[0] ?? null) === 'git') {
            return Process::result(output: $porcelain);
        }

        $stagingDir = $process->path;

        if (is_string($stagingDir) && $stagingDir !== '') {
            File::ensureDirectoryExists($stagingDir.'/vendor');
            File::put($stagingDir.'/vendor/autoload.php', '<?php // fake vendor autoload for tests');
        }

        return Process::result(exitCode: 0);
    });
}

it('packages a full zip: staged vendor build, phar, .env from .env.standalone, prebuilt assets, and excludes every decoy', function () {
    fakeReleaseProcesses();

    $this->artisan('zenon:release:package', ['--out' => $this->outDir])
        ->assertSuccessful();

    $zipPath = $this->outDir.'/zenonerp-'.config('zenon.platform_version').'.zip';
    expect(is_file($zipPath))->toBeTrue();

    $names = releaseZipEntryNames($zipPath);

    expect($names)->toContain('artisan')
        ->toContain('composer.json')
        ->toContain('composer.lock')
        ->toContain('.env.example')
        ->toContain('public/build/manifest.json')
        ->toContain('public/.htaccess')
        ->toContain('bin/composer.phar')
        ->toContain('vendor/autoload.php')
        ->toContain('.env')
        ->toContain('storage/logs/.gitignore')
        ->toContain('bootstrap/app.php')
        ->toContain('bootstrap/providers.php')
        ->toContain('bootstrap/cache/.gitignore')
        ->toContain('config/app.php')
        ->toContain('app/Foundation/Placeholder.php')
        ->toContain('database/.gitignore')
        ->toContain('database/migrations/2024_01_01_000000_create_x_table.php')
        ->toContain('modules/zenon/Core/module.json')
        ->toContain('modules/thirdparty/.gitkeep');

    // Staged .env comes from .env.standalone (no secrets), never the real dev .env.
    $env = releaseZipEntryContents($zipPath, '.env');
    expect($env)->toContain('ZENON_MODE=standalone')
        ->not->toContain('super-secret-real-dev-env');

    foreach ($names as $name) {
        expect($name)
            ->not->toBe('.env.standalone') // staged only as .env, never shipped under its own name too
            ->not->toBe('public/hot')
            ->not->toBe('CLAUDE.md')
            ->not->toBe('modules_statuses.json')
            ->not->toBe('storage/logs/laravel.log')
            ->not->toBe('bootstrap/cache/services.php')
            ->not->toBe('database/database.sqlite')
            ->not->toBe('database/zenon_tenant_default.sqlite')
            ->not->toBe('database/leftover_test.sqlite')
            ->not->toStartWith('modules/thirdparty/Demo')
            ->not->toStartWith('tests/')
            ->not->toStartWith('packages/')
            ->not->toContain('node_modules')
            ->not->toContain('/tests/');
    }
});

it('packages an update zip: no modules/thirdparty entry at all, and no .env', function () {
    fakeReleaseProcesses();

    $this->artisan('zenon:release:package', ['--update' => true, '--out' => $this->outDir])
        ->assertSuccessful();

    $zipPath = $this->outDir.'/zenonerp-'.config('zenon.platform_version').'-update.zip';
    expect(is_file($zipPath))->toBeTrue();

    $names = releaseZipEntryNames($zipPath);

    expect($names)->toContain('public/build/manifest.json')
        ->toContain('vendor/autoload.php')
        ->toContain('bin/composer.phar')
        ->toContain('modules/zenon/Core/module.json');

    foreach ($names as $name) {
        expect($name)->not->toContain('modules/thirdparty')
            ->not->toBe('.env');
    }
});

it('fails preflight when public/build/manifest.json is missing, producing no zip', function () {
    File::delete($this->sourceRoot.'/public/build/manifest.json');

    $this->artisan('zenon:release:package', ['--out' => $this->outDir])
        ->expectsOutputToContain('public/build/manifest.json')
        ->assertFailed();

    expect(is_file($this->outDir.'/zenonerp-'.config('zenon.platform_version').'.zip'))->toBeFalse();
});

it('fails preflight when the bundled composer.phar is missing, with the fetch one-liner in the output', function () {
    File::delete($this->pharPath);

    $this->artisan('zenon:release:package', ['--out' => $this->outDir])
        ->expectsOutputToContain(
            'php -r "copy(\'https://getcomposer.org/download/latest-2.x/composer.phar\',\'bin/composer.phar\');"'
        )
        ->assertFailed();

    expect(is_file($this->outDir.'/zenonerp-'.config('zenon.platform_version').'.zip'))->toBeFalse();
});

it('fails preflight when the working tree is dirty', function () {
    fakeReleaseProcesses(porcelain: " M app/Foundation/Release/ReleasePackager.php\n");

    $this->artisan('zenon:release:package', ['--out' => $this->outDir])
        ->expectsOutputToContain('is not clean')
        ->assertFailed();

    expect(is_file($this->outDir.'/zenonerp-'.config('zenon.platform_version').'.zip'))->toBeFalse();
});

it('--allow-dirty proceeds past a dirty working tree and still produces a zip', function () {
    fakeReleaseProcesses(porcelain: " M some/file.php\n");

    $this->artisan('zenon:release:package', ['--out' => $this->outDir, '--allow-dirty' => true])
        ->assertSuccessful();

    $zipPath = $this->outDir.'/zenonerp-'.config('zenon.platform_version').'.zip';
    expect(is_file($zipPath))->toBeTrue();
});

/*
 * Regression coverage for the review finding: staging is now derived from source_root
 * (base_path() in production), which means it genuinely sits INSIDE the storage/ root
 * this pipeline walks. Without pruning storage/app/zenon-tmp (self-inclusion + stale
 * crashed-run leftovers) and storage/app/releases (the out_dir default — old zips'
 * sibling .gitignore files) from DESCENT, every real release zip would ship junk
 * `storage/app/zenon-tmp/release-<uniqid>/.../.gitignore` entries.
 */

it('never ships the packager\'s own staging subtree or the out_dir-default subtree, including a stale leftover', function () {
    fakeReleaseProcesses();

    $this->artisan('zenon:release:package', ['--out' => $this->outDir])
        ->assertSuccessful();

    $zipPath = $this->outDir.'/zenonerp-'.config('zenon.platform_version').'.zip';
    $names = releaseZipEntryNames($zipPath);

    // Direct reproduction: staging now lives inside the fixture source root during the
    // run, so absolutely no entry may reference either subtree, at any depth.
    foreach ($names as $name) {
        expect($name)->not->toContain('storage/app/zenon-tmp')
            ->not->toContain('storage/app/releases');
    }

    // The specific planted decoys (a stale crashed-run leftover, and an old zip's
    // sibling .gitignore) must never ship either.
    expect($names)->not->toContain('storage/app/zenon-tmp/release-stale/foo/.gitignore')
        ->not->toContain('storage/app/releases/.gitignore');
});

it('never ships storage/framework/installed.lock (the installer-completion lock must survive every update)', function () {
    fakeReleaseProcesses();

    $this->artisan('zenon:release:package', ['--out' => $this->outDir])
        ->assertSuccessful();

    $zipPath = $this->outDir.'/zenonerp-'.config('zenon.platform_version').'.zip';
    $names = releaseZipEntryNames($zipPath);

    expect($names)->not->toContain('storage/framework/installed.lock');
});

it('throws on a mid-pipeline composer failure and leaves no new staging dir behind (finally cleanup)', function () {
    $zenonTmp = $this->sourceRoot.'/storage/app/zenon-tmp';
    $before = array_values(array_filter(
        scandir($zenonTmp) ?: [],
        fn ($entry) => str_starts_with($entry, 'release-'),
    ));

    Process::fake(function ($process) {
        $command = $process->command;

        if (is_array($command) && ($command[0] ?? null) === 'git') {
            return Process::result(output: '');
        }

        // The composer install invocation — force a failure, never touching
        // vendor/autoload.php, so installVendor()'s failure branch fires.
        return Process::result(exitCode: 1, errorOutput: 'composer install failed (fixture-forced failure)');
    });

    expect(fn () => app(ReleasePackager::class)->package(outDir: $this->outDir))
        ->toThrow(RuntimeException::class);

    $after = array_values(array_filter(
        scandir($zenonTmp) ?: [],
        fn ($entry) => str_starts_with($entry, 'release-'),
    ));

    // Only the pre-existing "release-stale" decoy remains — no NEW release-* dir from
    // this failed run survived, proving the `finally` cleanup ran.
    expect($after)->toBe($before);
});
