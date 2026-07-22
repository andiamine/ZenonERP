<?php

namespace App\Foundation\Installer;

/**
 * Replace-or-append `.env` line editor (CLAUDE.md §7 Phase 8 Task 6). Deliberately a
 * pure class — no facades, no config lookups, no framework dependency beyond plain PHP
 * file functions — so it is unit-testable against throwaway temp files without booting
 * the framework. Callers (Actions/WriteEnvironment, InstallerController) resolve the
 * actual target path (`config('zenon.installer.env_path') ?? App::environmentFilePath()`)
 * and pass it in explicitly.
 *
 * write() preserves every line it doesn't need to touch — comments, blank lines, and
 * unrelated `KEY=value` pairs keep their original position and order; only keys present
 * in $pairs are replaced in place (existing key) or appended at the end (new key, in the
 * order given). The write itself is atomic: content lands in `{path}.tmp` first, then an
 * OS-level rename() replaces the real file — a crash mid-write can never leave a
 * half-written .env behind.
 */
final class EnvWriter
{
    /**
     * @param  array<string, string>  $pairs
     */
    public function write(string $path, array $pairs): void
    {
        $lines = $this->readLines($path);
        $remaining = $pairs;

        foreach ($lines as $index => $line) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=/', $line, $matches) !== 1) {
                continue;
            }

            if (! array_key_exists($matches[1], $remaining)) {
                continue;
            }

            $lines[$index] = $matches[1].'='.$this->formatValue((string) $remaining[$matches[1]]);
            unset($remaining[$matches[1]]);
        }

        foreach ($remaining as $key => $value) {
            $lines[] = $key.'='.$this->formatValue((string) $value);
        }

        $content = $lines === [] ? '' : implode("\n", $lines)."\n";

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $tmpPath = $path.'.tmp';

        file_put_contents($tmpPath, $content);
        rename($tmpPath, $path);
    }

    /**
     * Parses an existing env file into key => value pairs (quotes stripped). Missing
     * file returns an empty array — the installer's status endpoint relies on this to
     * check "no .env written yet" without a file_exists() dance of its own.
     *
     * @return array<string, string>
     */
    public function read(string $path): array
    {
        $result = [];

        foreach ($this->readLines($path) as $line) {
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/', $line, $matches) === 1) {
                $result[$matches[1]] = $this->unquote($matches[2]);
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function readLines(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $raw = (string) file_get_contents($path);

        if ($raw === '') {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = explode("\n", $normalized);

        // A file written by write() (or any editor) ends with a trailing newline, which
        // explode() turns into one extra empty element — drop it so round-tripping
        // through write() never grows a blank line at the end. $lines is guaranteed
        // non-empty here (explode() on the non-'' $normalized above always yields at
        // least one element), so there's nothing to guard beyond the emptiness check.
        if (end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    private function formatValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s#"$]/', $value) === 1) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

            return '"'.$escaped.'"';
        }

        return $value;
    }

    private function unquote(string $value): string
    {
        if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
            $inner = substr($value, 1, -1);

            return str_replace(['\\"', '\\\\'], ['"', '\\'], $inner);
        }

        return $value;
    }
}
