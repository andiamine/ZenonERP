<?php

namespace App\Foundation\Support;

use RuntimeException;
use ZipArchive;

/**
 * Recursive directory-to-zip walker, extracted from the addon packager
 * (`ModulePackageCommand`, CLAUDE.md §7 Phase 7 Task 8) as a pure refactor so the
 * release packager (Phase 8 Task 12) can reuse it against the whole app tree instead
 * of duplicating the walk. Zip entries always use forward slashes, matching the
 * ZipArchive convention regardless of the host's DIRECTORY_SEPARATOR.
 *
 * Two independent exclusion mechanisms with DIFFERENT scopes:
 * - `excludedDirNames`: DIRECTORY-ONLY basename match at ANY depth (`node_modules`,
 *   `.git`, `tests`) — what the original addon packager needed. Never matched against
 *   files, so a file literally named e.g. `tests` is never excluded by this mechanism.
 * - `excludedRelativePaths`: matched against BOTH directories and files, as a
 *   normalized forward-slash path measured from the addTree() ROOT (`$dir`),
 *   independent of `$prefix` and of nesting depth — what the release packager needs
 *   (`public/hot`, a FILE — the Vite dev-server marker — as well as directories like
 *   `modules/thirdparty`). This is deliberately NOT matched against the zip entry
 *   name: a prefix must only change where content lands in the archive, never what
 *   gets excluded.
 */
final class ZipBuilder
{
    private function __construct(private readonly ZipArchive $zip) {}

    public static function create(string $zipPath): self
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException(sprintf('Could not create zip at [%s].', $zipPath));
        }

        return new self($zip);
    }

    /**
     * @param  list<string>  $excludedDirNames
     * @param  list<string>  $excludedRelativePaths
     */
    public function addTree(string $dir, string $prefix = '', array $excludedDirNames = [], array $excludedRelativePaths = []): self
    {
        $this->addDirectory($dir, trim(str_replace('\\', '/', $prefix), '/'), '', $excludedDirNames, $this->normalizeRelativePaths($excludedRelativePaths));

        return $this;
    }

    public function close(): void
    {
        $this->zip->close();
    }

    /**
     * @param  list<string>  $excludedDirNames
     * @param  list<string>  $excludedRelativePaths  already normalized (forward-slash, no leading/trailing slash)
     */
    private function addDirectory(string $dir, string $zipEntryPrefix, string $relativePrefix, array $excludedDirNames, array $excludedRelativePaths): void
    {
        $entries = scandir($dir) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $dir.DIRECTORY_SEPARATOR.$entry;
            $zipEntry = $zipEntryPrefix === '' ? $entry : $zipEntryPrefix.'/'.$entry;
            $relativeEntry = $relativePrefix === '' ? $entry : $relativePrefix.'/'.$entry;

            if (is_dir($fullPath)) {
                if (in_array($entry, $excludedDirNames, true) || in_array($relativeEntry, $excludedRelativePaths, true)) {
                    continue;
                }

                $this->addDirectory($fullPath, $zipEntry, $relativeEntry, $excludedDirNames, $excludedRelativePaths);

                continue;
            }

            if (in_array($relativeEntry, $excludedRelativePaths, true)) {
                continue;
            }

            $this->zip->addFile($fullPath, $zipEntry);
        }
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function normalizeRelativePaths(array $paths): array
    {
        return array_map(
            static fn (string $path): string => trim(str_replace('\\', '/', $path), '/'),
            $paths,
        );
    }
}
