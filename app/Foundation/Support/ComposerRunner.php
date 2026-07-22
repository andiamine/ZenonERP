<?php

namespace App\Foundation\Support;

use App\Foundation\Modules\AddonZipInstaller;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

/**
 * Phar-aware Composer invocation (CLAUDE.md §7 Phase 7 carry-forward, resolved Phase 8
 * Task 9): shells out to bare `composer` on PATH — which doesn't exist on Plesk/cPanel —
 * unless the release zip's bundled {@see config('zenon.composer.phar_path')} is present
 * on disk, in which case it invokes the phar directly with an explicit PHP binary as an
 * ARRAY command (`[php, phar, ...args]`, never string-concatenated — the array form is
 * what lets Symfony\Process build argv without a shell, so paths containing spaces on
 * commodity hosting never need quoting). Runs via the {@see Process} FACADE (not the
 * `Illuminate\Process\Factory` contract directly) specifically so `Process::fake()`
 * intercepts it in tests exactly like {@see AddonZipInstaller}'s
 * prior direct facade call did.
 */
final class ComposerRunner
{
    /**
     * @param  list<string>  $args
     */
    public function run(string $workingDir, array $args, int $timeoutSeconds = 600): ProcessResult
    {
        $pharPath = (string) config('zenon.composer.phar_path');

        $command = is_file($pharPath)
            ? [$this->phpBinary(), $pharPath, ...$args]
            : 'composer '.implode(' ', $args);

        return Process::path($workingDir)->timeout($timeoutSeconds)->run($command);
    }

    /**
     * Resolution order for the PHP binary the phar is invoked with:
     *
     * 1. `config('zenon.composer.php_binary')` — explicit Plesk override (e.g. a
     *    version-suffixed binary like `/opt/plesk/php/8.3/bin/php`) when the operator
     *    has set one; always wins when present.
     * 2. `PHP_BINARY`, but ONLY when `PHP_SAPI === 'cli'` — under php-fpm (or any other
     *    non-CLI SAPI) `PHP_BINARY` points at the fpm master binary, which cannot run an
     *    arbitrary script; this guard is precisely what makes it safe to later drive
     *    this class from the deferred (Phase 9/M2) web-upload admin UI, which runs under
     *    php-fpm, without silently invoking a binary that can't execute the phar.
     * 3. `PHP_BINDIR . '/php'` (and, since a Windows CLI's PHP_BINDIR holds `php.exe`
     *    rather than a bare `php`, also `PHP_BINDIR . '/php.exe'`) — whichever of the two
     *    actually exists on disk.
     * 4. Bare `'php'` as the last resort, relying on PATH.
     */
    private function phpBinary(): string
    {
        $configured = config('zenon.composer.php_binary');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (PHP_SAPI === 'cli') {
            return PHP_BINARY;
        }

        $plain = rtrim(PHP_BINDIR, '/\\').DIRECTORY_SEPARATOR.'php';
        $windows = $plain.'.exe';

        if (is_file($windows)) {
            return $windows;
        }

        if (is_file($plain)) {
            return $plain;
        }

        return 'php';
    }
}
