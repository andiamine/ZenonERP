<?php

namespace App\Foundation\Modules;

use Composer\Autoload\ClassLoader;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Nwidart\Modules\FileRepository;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Zip-install pipeline for third-party addons (CLAUDE.md §7/§12 Phase 7 Task 8): extracts
 * an addon zip into {@see config('zenon.thirdparty_path')} and runs the NORMAL
 * {@see ModuleManager::install()} flow — no SPA rebuild, no Node on the server. The single
 * public entry point, {@see self::install()}, is ordered so every refusal happens before
 * anything is written under the real thirdparty path; a failure after that point rolls the
 * target directory back. Nothing here is a sanctioned extension point for other modules —
 * this is platform-only zip handling (§13 risk #1: keep the enablement authority narrow).
 *
 * Live-loader registration (see {@see self::registerLiveLoader()}) exists for a subtle
 * reason: `composer dump-autoload` regenerates vendor/composer/autoload_psr4.php on disk
 * (and, in production, wikimedia/composer-merge-plugin folds the addon's own composer.json
 * into that regeneration because it lives under the real modules/thirdparty/* glob) — but
 * the Composer ClassLoader instance already booted for THIS PHP process is memoized by
 * `require vendor/autoload.php` and never re-reads those regenerated files. Without patching
 * the live loader, ManifestValidator's `class_exists($provider)` check — which
 * `ModuleManager::install()` runs immediately afterwards — would fail for a genuinely new
 * addon's provider class within this same CLI invocation, even though dump-autoload
 * succeeded. The NEXT process (the next artisan invocation) autoloads fine regardless; this
 * patches only the current one.
 */
final class AddonZipInstaller
{
    public function __construct(
        private readonly ModuleManager $manager,
        private readonly ModuleRegistry $registry,
        private readonly RepositoryInterface $modules,
    ) {}

    /**
     * Installs the addon at $zipPath and returns its manifest alias. Every refusal is a
     * plain RuntimeException with an actionable message and leaves zero residue on disk:
     * refusals before the target directory is created (steps 1-4) only ever touch a
     * throwaway temp directory (cleaned in the `finally` below); a failure after the
     * target is populated (steps 6-9) deletes the target directory before rethrowing.
     */
    public function install(string $zipPath): string
    {
        $zip = new ZipArchive;
        $opened = $zip->open($zipPath);

        if ($opened !== true) {
            // ZipArchive::open() returns `true` on success or an int error code (ZipArchive::ER_*)
            // on failure — the PHPStan stub already narrows $opened to int here.
            throw new RuntimeException(sprintf(
                '[%s] is not a readable zip (ZipArchive::open returned error code %d).',
                $zipPath,
                $opened,
            ));
        }

        $temp = storage_path('app'.DIRECTORY_SEPARATOR.'zenon-tmp'.DIRECTORY_SEPARATOR.uniqid('addon-', true));

        try {
            [$manifestJson, $prefix] = $this->locateManifest($zip);
            $manifest = $this->parseManifest($manifestJson, $zipPath);
            ['name' => $name, 'alias' => $alias] = $this->preflight($manifest);

            $target = $this->targetPath($name);

            $this->extractTo($zip, $prefix, $temp);

            if (! File::moveDirectory($temp, $target)) {
                throw new RuntimeException(sprintf('Failed to move extracted addon from [%s] to [%s].', $temp, $target));
            }

            try {
                $this->dumpAutoload($target);
                $this->registerLiveLoader($target);
                $this->rescan();

                $this->manager->install($alias);
            } catch (Throwable $e) {
                File::deleteDirectory($target);

                throw $e;
            }

            return $alias;
        } finally {
            $zip->close();
            File::deleteDirectory($temp);
        }
    }

    /**
     * Locates module.json at the archive root, or — tolerating exactly one top-level
     * wrapper directory (what Compress-Archive/Finder produce) — inside it.
     *
     * @return array{0: string, 1: string} [raw module.json contents, entry-name prefix to strip]
     */
    private function locateManifest(ZipArchive $zip): array
    {
        $direct = $zip->getFromName('module.json');

        if ($direct !== false) {
            return [$direct, ''];
        }

        $topSegments = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false || $name === '') {
                continue;
            }

            $segment = explode('/', str_replace('\\', '/', $name), 2)[0];

            if ($segment !== '') {
                $topSegments[$segment] = true;
            }
        }

        if (count($topSegments) === 1) {
            $wrapper = array_key_first($topSegments);
            $wrapped = $zip->getFromName($wrapper.'/module.json');

            if ($wrapped !== false) {
                return [$wrapped, $wrapper.'/'];
            }
        }

        throw new RuntimeException(
            'Zip does not contain a module.json at its root or inside a single top-level wrapper directory.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseManifest(string $raw, string $zipPath): array
    {
        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(sprintf('[%s] contains an invalid module.json (not valid JSON).', $zipPath));
        }

        return $decoded;
    }

    /**
     * Pre-flight checks (CLAUDE.md §12 Phase 7 Task 8 step 3), all BEFORE anything is
     * written under the real thirdparty path.
     *
     * @param  array<string, mixed>  $manifest
     * @return array{name: string, alias: string}
     */
    private function preflight(array $manifest): array
    {
        $name = $manifest['name'] ?? null;
        $alias = $manifest['alias'] ?? null;
        $zenon = $manifest['zenon'] ?? null;
        $id = is_array($zenon) ? ($zenon['id'] ?? null) : null;
        $platform = is_array($zenon) ? ($zenon['platform'] ?? null) : null;
        $frontend = is_array($zenon) ? ($zenon['frontend'] ?? null) : null;
        $remote = is_array($frontend) ? ($frontend['remote'] ?? null) : null;

        if (! is_string($name) || $name === ''
            || ! is_string($alias) || $alias === ''
            || ! is_string($id) || $id === ''
            || ! is_string($platform) || $platform === ''
        ) {
            throw new RuntimeException(
                'module.json is missing one or more required fields: name, alias, zenon.id, zenon.platform.'
            );
        }

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) !== 1) {
            throw new RuntimeException(sprintf(
                'Module name [%s] must match ^[A-Z][A-Za-z0-9]*$ — it also names the target folder.',
                $name,
            ));
        }

        // The install-time semver check just below accepts full composer/semver, but the
        // SPA runtime loader (resources/js/core/remoteModules.ts platformSatisfies) only
        // understands `*`, `^MAJOR[.MINOR[.PATCH]]`, or a bare `MAJOR[.MINOR[.PATCH]]` — an
        // addon with, say, `~1.2` and a remote frontend would install cleanly here and then
        // be refused forever at mount time with no actionable signal. Addons without a
        // remote frontend (backend-only) keep full semver freedom — the loader never
        // evaluates their platform string.
        if (is_string($remote) && $remote !== '' && preg_match('/^(\*|\^?\d+(\.\d+){0,2})$/', $platform) !== 1) {
            throw new RuntimeException(sprintf(
                'Addon declares a remote frontend (zenon.frontend.remote) but platform [%s] is not in a '
                .'form the SPA loader understands: addons with a remote frontend must declare platform as '
                .'^MAJOR[.MINOR[.PATCH]], an exact version, or * — the SPA loader does not evaluate other '
                .'constraints.',
                $platform,
            ));
        }

        $platformVersion = (string) config('zenon.platform_version');

        if (! Semver::satisfies($platformVersion, $platform)) {
            throw new RuntimeException(sprintf(
                'Addon requires platform [%s], but this platform is version [%s].',
                $platform, $platformVersion,
            ));
        }

        $target = $this->targetPath($name);

        if (is_dir($target)) {
            throw new RuntimeException(sprintf(
                'Target directory [%s] already exists — install-zip does not support upgrades yet.',
                $target,
            ));
        }

        if (array_key_exists($alias, $this->registry->discovered())) {
            throw new RuntimeException(sprintf(
                'Module alias [%s] is already discovered on this platform — refusing to install a duplicate.',
                $alias,
            ));
        }

        return ['name' => $name, 'alias' => $alias];
    }

    /**
     * Streams every zip entry to $tempDir entry-by-entry (never bulk `extractTo`, so the
     * name filter below is authoritative) with zip-slip protection: any entry whose name,
     * after stripping the wrapper prefix, contains a ".." segment, starts with "/" or a
     * drive letter, or contains a backslash refuses the WHOLE install. Symlink entries
     * (unix external-attributes mode 0120000) are silently skipped — ZipArchive::extractTo
     * doesn't materialize symlinks on Windows either, but streaming write means we control
     * this explicitly rather than relying on platform behavior.
     */
    private function extractTo(ZipArchive $zip, string $prefix, string $tempDir): void
    {
        File::ensureDirectoryExists($tempDir);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false) {
                continue;
            }

            $normalized = str_replace('\\', '/', $name);

            if ($prefix !== '') {
                if (! str_starts_with($normalized, $prefix)) {
                    continue;
                }

                $relative = substr($normalized, strlen($prefix));
            } else {
                $relative = $normalized;
            }

            if ($relative === '') {
                continue; // the wrapper directory entry itself
            }

            $this->assertSafeEntryName($name, $relative);

            if ($this->isSymlinkEntry($zip, $i)) {
                continue;
            }

            if (str_ends_with($relative, '/')) {
                File::ensureDirectoryExists($tempDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, rtrim($relative, '/')));

                continue;
            }

            $destination = $tempDir.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            File::ensureDirectoryExists(dirname($destination));

            $stream = $zip->getStream($name);

            if ($stream === false) {
                throw new RuntimeException(sprintf('Could not read zip entry [%s].', $name));
            }

            $contents = stream_get_contents($stream);
            fclose($stream);

            if ($contents === false) {
                throw new RuntimeException(sprintf('Failed to read zip entry [%s].', $name));
            }

            File::put($destination, $contents);
        }
    }

    /** Zip-slip guard — checked against the RAW entry name (backslash) and the relative path (traversal/absolute). */
    private function assertSafeEntryName(string $rawName, string $relative): void
    {
        if (str_contains($rawName, '\\')) {
            throw new RuntimeException(sprintf('Zip entry [%s] contains a backslash — refusing (zip-slip guard).', $rawName));
        }

        if (str_starts_with($relative, '/')) {
            throw new RuntimeException(sprintf('Zip entry [%s] is an absolute path — refusing (zip-slip guard).', $rawName));
        }

        if (preg_match('#^[A-Za-z]:#', $relative) === 1) {
            throw new RuntimeException(sprintf('Zip entry [%s] starts with a drive letter — refusing (zip-slip guard).', $rawName));
        }

        if (in_array('..', explode('/', $relative), true)) {
            throw new RuntimeException(sprintf('Zip entry [%s] contains a ".." path segment — refusing (zip-slip guard).', $rawName));
        }
    }

    /**
     * ZipArchive::getExternalAttributesIndex() only reports meaningful unix mode bits when
     * the entry was written on a unix opsys; entries from other opsys values (e.g. FAT,
     * written by Windows zip tools) never carry a symlink mode and are never skipped here.
     */
    private function isSymlinkEntry(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attr = 0;

        if (! $zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            return false;
        }

        if ($opsys !== ZipArchive::OPSYS_UNIX) {
            return false;
        }

        return (($attr >> 16) & 0170000) === 0120000; // S_IFLNK
    }

    private function dumpAutoload(string $target): void
    {
        $result = Process::path(base_path())->run('composer dump-autoload');

        if ($result->failed()) {
            throw new RuntimeException(sprintf(
                'composer dump-autoload failed after extracting the addon to [%s] (exit code %s): %s',
                $target,
                $result->exitCode(),
                trim($result->errorOutput() !== '' ? $result->errorOutput() : $result->output()),
            ));
        }
    }

    /**
     * Patches the ALREADY-BOOTED Composer ClassLoader for this process — see the class
     * docblock for why `composer dump-autoload` alone isn't enough.
     */
    private function registerLiveLoader(string $target): void
    {
        $composerJsonPath = $target.DIRECTORY_SEPARATOR.'composer.json';

        if (! is_file($composerJsonPath)) {
            return;
        }

        $composer = json_decode((string) file_get_contents($composerJsonPath), true);
        $psr4 = is_array($composer) ? ($composer['autoload']['psr-4'] ?? null) : null;

        if (! is_array($psr4)) {
            return;
        }

        /** @var ClassLoader $loader */
        $loader = require base_path('vendor/autoload.php');

        foreach ($psr4 as $prefix => $path) {
            if (! is_string($prefix) || ! is_string($path)) {
                continue;
            }

            $loader->addPsr4($prefix, $target.DIRECTORY_SEPARATOR.rtrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR));
        }
    }

    /**
     * Forces nwidart's repository to re-read disk. FileRepository::scan() memoizes into a
     * PRIVATE STATIC property shared across every instance/subclass in the process — a
     * fresh RepositoryInterface instance would not see the new addon either, so the only
     * way to invalidate it is FileRepository's own resetModules() (not part of
     * RepositoryInterface — verified against v13's bound LaravelFileRepository). Then flush
     * ModuleRegistry's own per-instance discovered()/installed() memoization.
     */
    private function rescan(): void
    {
        if ($this->modules instanceof FileRepository) {
            $this->modules->resetModules();
        }

        $this->registry->flush();
    }

    private function targetPath(string $name): string
    {
        return rtrim((string) config('zenon.thirdparty_path'), '/\\').DIRECTORY_SEPARATOR.$name;
    }
}
