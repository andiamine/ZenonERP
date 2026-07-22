<?php

namespace App\Foundation\Release;

use App\Console\Commands\ReleasePackageCommand;
use App\Foundation\Modules\AddonZipInstaller;
use App\Foundation\Support\ComposerRunner;
use App\Foundation\Support\ZipBuilder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Builds the release zip that installs ZenonERP on a bare Apache+MySQL host with no
 * shell, no Node, and no Composer (CLAUDE.md §7/§12 Phase 8 Task 12) — `zenon:release:
 * package`'s fat service (thin command: {@see ReleasePackageCommand}).
 *
 * Pipeline (order matters — every preflight check runs BEFORE anything is staged):
 *  1. Preflight: the committed frontend registry is fresh (`zenon:frontend:generate
 *     --check`) → `public/build/manifest.json` exists (prebuilt SPA assets) → the
 *     bundled `composer.phar` exists → the working tree is git-clean (or --allow-dirty).
 *  2. Filtered staging COPY (never copy-then-delete — see {@see self::stageRootDirs()})
 *     of the allowlisted tree into a throwaway `storage/app/zenon-tmp/release-*` dir.
 *  3. `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
 *     --no-scripts` against the STAGING copy (never the real `vendor/`), then a HARD
 *     verify that `vendor/autoload.php` exists — catches a faked/silently-failing runner
 *     that still reports success.
 *  4. Copy the bundled phar to `bin/composer.phar` in staging (so a bare host can
 *     `zenon:module:install-zip` addons without a system Composer).
 *  5. Full zips only: stage `.env` from the committed `.env.standalone` template (no
 *     secrets — the installer wizard fills in real credentials on first boot). Update
 *     zips NEVER carry a `.env` — never overwrite a live install's configuration.
 *  6. Zip the (already-clean) staging dir via {@see ZipBuilder} — no further excludes
 *     needed, the copy already filtered everything.
 *  7. Staging cleanup always runs, success or failure (`finally`).
 *
 * NEVER pass `--classmap-authoritative` to the composer install above: it disables the
 * PSR-4 fallback that `AddonZipInstaller::registerLiveLoader()` (and any addon class
 * added to the tree after this vendor build was generated) depends on to resolve without
 * a fresh `require vendor/autoload.php` in a new process.
 */
final class ReleasePackager
{
    /**
     * Root-level files copied verbatim when present. Deliberately excludes the real
     * `.env` (never copied — see the `.env.standalone` staging step instead) and every
     * dev tool config (phpunit.xml, phpstan.neon.dist, eslint.config.js, tsconfig.json,
     * vite.config.ts, package*.json, .gitignore, CLAUDE.md, …): those are absent from
     * the release zip purely by NOT being named here.
     */
    private const ROOT_FILES = ['artisan', 'composer.json', 'composer.lock', '.env.example', 'README.md', 'LICENSE'];

    /**
     * Root-level directories walked recursively under the exclusion rules in
     * {@see self::copyFiltered()}. `vendor`, `node_modules`, `tests`, `packages`,
     * `stubs`, and every dotdir are absent purely by not being listed here.
     */
    private const ROOT_DIRS = ['app', 'bootstrap', 'config', 'database', 'docs', 'modules', 'public', 'resources', 'routes', 'storage'];

    /** Directory basenames excluded at ANY depth, under every one of the roots above. */
    private const EXCLUDED_DIR_NAMES = ['node_modules', '.git', 'tests'];

    /**
     * Root-relative (forward-slash) paths excluded wholesale, whether the entry turns
     * out to be a file, a directory, or (on the real repo) a symlink: `public/hot` is
     * the Vite dev-server marker FILE; `public/storage` is the `storage:link` symlink —
     * neither belongs in a release, and both would otherwise dangle or mislead on a
     * fresh extract.
     */
    private const EXCLUDED_RELATIVE_PATHS = ['public/hot', 'public/storage'];

    public function __construct(private readonly ComposerRunner $composer) {}

    public function package(bool $update = false, ?string $outDir = null, bool $allowDirty = false): ReleasePackageResult
    {
        $sourceRoot = rtrim((string) config('zenon.release.source_root'), '/\\');

        $this->assertRegistryFresh();
        $this->assertBuildManifestPresent($sourceRoot);
        $pharPath = $this->assertPharPresent();
        $warnings = $this->assertGitClean($sourceRoot, $allowDirty);

        $staging = storage_path('app/zenon-tmp/release-'.uniqid('', true));

        try {
            File::ensureDirectoryExists($staging);
            $this->stageRootFiles($sourceRoot, $staging);
            $this->stageRootDirs($sourceRoot, $staging, $update);

            $this->installVendor($staging);

            File::ensureDirectoryExists($staging.'/bin');
            File::copy($pharPath, $staging.'/bin/composer.phar');

            if (! $update) {
                $this->stageEnv($sourceRoot, $staging);
            }

            $zipPath = $this->buildZip($staging, $outDir, $update);

            return new ReleasePackageResult($zipPath, $warnings);
        } finally {
            File::deleteDirectory($staging);
        }
    }

    /**
     * Runs the REAL `zenon:frontend:generate --check` gate — never weakened for
     * packaging. The one thing this method does adjust is the scan-path CONTEXT it
     * runs under: `config/modules.php` appends `tests/Fixtures/modules/*` to
     * `modules.scan.paths` under `APP_ENV=testing` (the standing module-foundation
     * fixture suite — several of those fixtures declare a `frontend.entry`), so
     * packaging a release from within a test process would otherwise let fixture
     * modules leak into `ModuleRegistry::discovered()` and produce a false "stale"
     * failure that has nothing to do with the real committed registry. Every path
     * under `tests/` is stripped from `modules.scan.paths` for the DURATION of this one
     * call and restored immediately after (`finally`) — the check itself is untouched,
     * only the module set it evaluates against is made to reflect the real product,
     * regardless of which environment `zenon:release:package` happens to run in.
     * (nwidart's `FileRepository::scan()` bypasses its own static memoization entirely
     * while `app()->runningUnitTests()` is true, so this config swap alone is enough —
     * no `resetModules()` dance needed here, unlike {@see AddonZipInstaller},
     * which also has to work outside tests.)
     */
    private function assertRegistryFresh(): void
    {
        $originalScanPaths = config('modules.scan.paths');

        if (is_array($originalScanPaths)) {
            $testsPrefix = rtrim(str_replace('\\', '/', base_path('tests')), '/').'/';

            config(['modules.scan.paths' => array_values(array_filter(
                $originalScanPaths,
                fn ($path) => ! is_string($path) || ! str_starts_with(str_replace('\\', '/', $path), $testsPrefix),
            ))]);
        }

        try {
            $exitCode = Artisan::call('zenon:frontend:generate', ['--check' => true]);
        } finally {
            config(['modules.scan.paths' => $originalScanPaths]);
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(
                'zenon:frontend:generate --check failed — the committed module-registry.ts is stale. Run '
                .'`php artisan zenon:frontend:generate`, commit the result, and rebuild the SPA before packaging.'
            );
        }
    }

    private function assertBuildManifestPresent(string $sourceRoot): void
    {
        $manifest = $sourceRoot.'/public/build/manifest.json';

        if (! is_file($manifest)) {
            throw new RuntimeException(sprintf(
                'Prebuilt SPA assets not found: [%s] is missing — run `npm run build` before packaging a release.',
                $manifest,
            ));
        }
    }

    private function assertPharPresent(): string
    {
        $pharPath = (string) config('zenon.composer.phar_path');

        if (! is_file($pharPath)) {
            throw new RuntimeException(sprintf(
                'Bundled composer.phar not found at [%s]. Fetch one with: '
                .'php -r "copy(\'https://getcomposer.org/download/latest-2.x/composer.phar\',\'bin/composer.phar\');"',
                $pharPath,
            ));
        }

        return $pharPath;
    }

    /**
     * `git status --porcelain` in $sourceRoot, via the Process FACADE (not shell_exec)
     * so `Process::fake()` intercepts it in tests exactly like {@see ComposerRunner}.
     * Three outcomes:
     *  - command fails (non-zero exit): git isn't installed, or $sourceRoot isn't a
     *    repository — NOT fatal, warn and continue (a fixture source_root in tests is
     *    never a repo of its own).
     *  - command succeeds with empty output: clean tree, nothing to report.
     *  - command succeeds with non-empty output: dirty tree — fatal unless $allowDirty,
     *    in which case it's downgraded to a warning.
     *
     * @return list<string> warnings to surface (never fatal)
     */
    private function assertGitClean(string $sourceRoot, bool $allowDirty): array
    {
        $result = Process::path($sourceRoot)->run(['git', 'status', '--porcelain']);

        if (! $result->successful()) {
            return [sprintf(
                'Could not run `git status --porcelain` in [%s] (git not installed, or not a repository) — '
                .'skipping the clean-tree check.',
                $sourceRoot,
            )];
        }

        $porcelain = trim($result->output());

        if ($porcelain === '') {
            return [];
        }

        if ($allowDirty) {
            return [sprintf(
                'Working tree has uncommitted changes — continuing because --allow-dirty was passed: %s',
                $porcelain,
            )];
        }

        throw new RuntimeException(sprintf(
            'Working tree is not clean (git status --porcelain reported changes: %s) — commit or stash before '
            .'packaging a release, or pass --allow-dirty.',
            $porcelain,
        ));
    }

    private function stageRootFiles(string $sourceRoot, string $staging): void
    {
        foreach (self::ROOT_FILES as $file) {
            $src = $sourceRoot.'/'.$file;

            if (is_file($src)) {
                File::copy($src, $staging.'/'.$file);
            }
        }
    }

    private function stageRootDirs(string $sourceRoot, string $staging, bool $update): void
    {
        foreach (self::ROOT_DIRS as $dir) {
            $src = $sourceRoot.'/'.$dir;

            if (is_dir($src)) {
                $this->copyFiltered($src, $staging.'/'.$dir, $dir, $update);
            }
        }
    }

    /**
     * Recursive filtered copy — filters DURING the copy (never copy-then-delete, so
     * Demo's node_modules or a real vendor/node_modules tree is never actually walked
     * onto disk). $relPath is the forward-slash path measured from the source ROOT
     * (e.g. "modules/thirdparty", "bootstrap/cache", "storage/framework/views") — the
     * vocabulary every rule below matches against, independent of nesting/prefix.
     */
    private function copyFiltered(string $srcDir, string $destDir, string $relPath, bool $update): void
    {
        // modules/thirdparty gets its own full-vs-update handling (never a plain
        // recursive copy): a clean .gitkeep-only directory in full zips, and no entry
        // at all in update zips (installed addons on the target host must survive an
        // update untouched).
        if ($relPath === 'modules/thirdparty') {
            $this->stageThirdparty($srcDir, $destDir, $update);

            return;
        }

        File::ensureDirectoryExists($destDir);

        $entries = scandir($srcDir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $srcPath = $srcDir.'/'.$entry;
            $destPath = $destDir.'/'.$entry;
            $childRel = $relPath.'/'.$entry;

            if (in_array($childRel, self::EXCLUDED_RELATIVE_PATHS, true)) {
                continue;
            }

            if (is_dir($srcPath)) {
                if (in_array($entry, self::EXCLUDED_DIR_NAMES, true)) {
                    continue;
                }

                $this->copyFiltered($srcPath, $destPath, $childRel, $update);

                continue;
            }

            if ($this->isSkeletonOnlyPath($relPath) && $entry !== '.gitignore') {
                // storage/** and bootstrap/cache/** ship as SKELETONS: only the
                // .gitignore placeholder survives, so extraction recreates the
                // directories Laravel expects without shipping any cached/log/session
                // DATA (the lock file & real data thereby survive an update by
                // construction — they were never in the zip to begin with).
                continue;
            }

            if ($relPath === 'database' && $this->isExcludedDatabaseFile($entry)) {
                // database/database.sqlite (local dev DB) and database/zenon_tenant_*
                // (per-tenant sqlite test artifacts) never belong in a release.
                continue;
            }

            File::copy($srcPath, $destPath);
        }
    }

    private function isSkeletonOnlyPath(string $relPath): bool
    {
        return $relPath === 'storage' || str_starts_with($relPath, 'storage/')
            || $relPath === 'bootstrap/cache' || str_starts_with($relPath, 'bootstrap/cache/');
    }

    private function isExcludedDatabaseFile(string $entry): bool
    {
        return $entry === 'database.sqlite' || str_starts_with($entry, 'zenon_tenant_');
    }

    /**
     * Full zips: a clean modules/thirdparty/ containing ONLY .gitkeep (the Demo addon,
     * and anything else on disk under thirdparty, is deliberately excluded — a clean
     * product release; Demo ships separately via `zenon:module:package Demo`). Sourced
     * from the repo's own committed .gitkeep when present; synthesized empty otherwise,
     * so the directory always survives extraction regardless of what else lives there.
     *
     * Update zips: no modules/thirdparty/ entry at all — installed addons on the
     * target host must be completely untouched by an update.
     */
    private function stageThirdparty(string $srcDir, string $destDir, bool $update): void
    {
        if ($update) {
            return;
        }

        File::ensureDirectoryExists($destDir);

        $gitkeep = $srcDir.'/.gitkeep';

        if (is_file($gitkeep)) {
            File::copy($gitkeep, $destDir.'/.gitkeep');
        } else {
            File::put($destDir.'/.gitkeep', '');
        }
    }

    /**
     * `composer install --no-dev` against the STAGING copy, then a hard verify that
     * vendor/autoload.php actually landed — catches a Process fake (or a genuinely
     * broken composer invocation) that reports success without producing a usable
     * vendor tree.
     */
    private function installVendor(string $staging): void
    {
        $result = $this->composer->run($staging, [
            'install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction', '--no-scripts',
        ], 600);

        if ($result->failed()) {
            throw new RuntimeException(sprintf(
                'composer install --no-dev failed while staging the release (exit code %s): %s',
                $result->exitCode(),
                trim($result->errorOutput() !== '' ? $result->errorOutput() : $result->output()),
            ));
        }

        if (! is_file($staging.'/vendor/autoload.php')) {
            throw new RuntimeException(sprintf(
                'composer install reported success but [%s/vendor/autoload.php] is missing — refusing to '
                .'package a broken vendor tree.',
                $staging,
            ));
        }
    }

    private function stageEnv(string $sourceRoot, string $staging): void
    {
        $envStandalone = $sourceRoot.'/.env.standalone';

        if (! is_file($envStandalone)) {
            throw new RuntimeException(sprintf(
                '.env.standalone not found at [%s] — cannot stage the full release .env.',
                $envStandalone,
            ));
        }

        File::copy($envStandalone, $staging.'/.env');
    }

    private function buildZip(string $staging, ?string $outDir, bool $update): string
    {
        $outDir = $outDir !== null && $outDir !== '' ? $outDir : (string) config('zenon.release.out_dir');
        File::ensureDirectoryExists($outDir);

        $version = (string) config('zenon.platform_version');
        $zipName = sprintf('zenonerp-%s%s.zip', $version, $update ? '-update' : '');
        $zipPath = rtrim($outDir, '/\\').'/'.$zipName;

        $zip = ZipBuilder::create($zipPath);
        // Staging is already clean by construction (filtered during the copy above) —
        // no further excludes needed here.
        $zip->addTree($staging, '');
        $zip->close();

        return $zipPath;
    }
}
