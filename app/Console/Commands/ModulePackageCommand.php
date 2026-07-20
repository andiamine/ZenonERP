<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Packages a folder under {@see config('zenon.thirdparty_path')} into a distributable zip
 * for `zenon:module:install-zip` (CLAUDE.md §7/§12 Phase 7 Task 8) — the packaging half of
 * the zip pipeline the acceptance flow exercises against the Demo addon. Zip contents sit
 * at the archive ROOT (module.json top-level, no wrapper directory).
 */
class ModulePackageCommand extends Command
{
    protected $signature = 'zenon:module:package {name : Module folder name under the thirdparty path} {--out= : Output directory (default: storage/app/packages)}';

    protected $description = 'Package a third-party addon folder into a distributable zip';

    /** Directories never shipped in the zip, regardless of nesting depth. */
    private const EXCLUDED_DIRS = ['node_modules', '.git', 'tests'];

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $source = rtrim((string) config('zenon.thirdparty_path'), '/\\').DIRECTORY_SEPARATOR.$name;
        $manifestPath = $source.DIRECTORY_SEPARATOR.'module.json';

        if (! is_dir($source) || ! is_file($manifestPath)) {
            $this->components->error(sprintf(
                '[%s] does not exist or has no module.json under [%s].',
                $name, config('zenon.thirdparty_path'),
            ));

            return self::FAILURE;
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($decoded)) {
            $this->components->error(sprintf('[%s]/module.json is not valid JSON.', $name));

            return self::FAILURE;
        }

        /** @var array<string, mixed> $zenon */
        $zenon = is_array($decoded['zenon'] ?? null) ? $decoded['zenon'] : [];
        $id = (string) ($zenon['id'] ?? '');
        $version = (string) ($zenon['version'] ?? '');
        $manifestName = (string) ($decoded['name'] ?? $name);

        if (! str_contains($id, '/') || $version === '') {
            $this->components->error(sprintf(
                '[%s]/module.json is missing zenon.id (vendor/name) or zenon.version.', $name,
            ));

            return self::FAILURE;
        }

        /** @var array<string, mixed> $frontend */
        $frontend = is_array($zenon['frontend'] ?? null) ? $zenon['frontend'] : [];
        $remote = $frontend['remote'] ?? null;

        if (is_string($remote) && $remote !== '') {
            $remotePath = $source.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $remote);

            if (! is_file($remotePath)) {
                $this->components->error(sprintf(
                    '[%s] declares zenon.frontend.remote [%s] but that file is missing at [%s] — build the addon before packaging.',
                    $name, $remote, $remotePath,
                ));

                return self::FAILURE;
            }
        }

        $outDir = (string) ($this->option('out') ?? '');
        $outDir = $outDir !== '' ? $outDir : storage_path('app/packages');
        File::ensureDirectoryExists($outDir);

        $vendor = Str::before($id, '/');
        $zipName = sprintf('%s-%s-%s.zip', $vendor, Str::lower($manifestName), $version);
        $zipPath = rtrim($outDir, '/\\').DIRECTORY_SEPARATOR.$zipName;

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->components->error(sprintf('Could not create zip at [%s].', $zipPath));

            return self::FAILURE;
        }

        $this->addDirectory($zip, $source, '');
        $zip->close();

        $this->components->info(sprintf('Packaged [%s] to [%s].', $name, $zipPath));

        return self::SUCCESS;
    }

    private function addDirectory(ZipArchive $zip, string $dir, string $zipPrefix): void
    {
        $entries = scandir($dir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $dir.DIRECTORY_SEPARATOR.$entry;
            $zipEntry = $zipPrefix === '' ? $entry : $zipPrefix.'/'.$entry;

            if (is_dir($fullPath)) {
                if (in_array($entry, self::EXCLUDED_DIRS, true)) {
                    continue;
                }

                $this->addDirectory($zip, $fullPath, $zipEntry);

                continue;
            }

            $zip->addFile($fullPath, $zipEntry);
        }
    }
}
