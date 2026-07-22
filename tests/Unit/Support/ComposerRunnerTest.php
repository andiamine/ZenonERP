<?php

use App\Foundation\Support\ComposerRunner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Phase 8 Task 9: ComposerRunner picks an ARRAY command `[php, phar, ...args]` when the
 * configured composer.phar exists on disk (the Plesk/cPanel no-shell path), else falls
 * back to the STRING `composer ...` form (dev machines, current behavior). Needs a
 * booted app (config()/Process facade), so this binds Tests\TestCase like
 * DatabaseConnectionProbeTest, unlike the framework-free EnvWriterTest/ZipBuilderTest.
 *
 * Command-shape assertions read `$process->command` off the PendingProcess instance
 * PHPUnit's Process::assertRan() closure receives as its first argument — Laravel keeps
 * the ORIGINAL array|string given to run() there (distinct from
 * Symfony\Process::getCommandline(), which is always a flattened string) — so this is
 * the one true way to assert "was this an array command or a string command" against
 * the fake.
 */
uses(TestCase::class);

beforeEach(function () {
    $this->pharPath = storage_path('framework/testing/composer-runner-'.uniqid().'/composer.phar');
    Process::fake();
});

afterEach(function () {
    File::deleteDirectory(dirname($this->pharPath));
});

it('runs an ARRAY command [phpBinary, pharPath, ...args] when the configured phar exists on disk', function () {
    File::ensureDirectoryExists(dirname($this->pharPath));
    File::put($this->pharPath, 'not a real phar, just needs to exist');

    config(['zenon.composer.phar_path' => $this->pharPath]);

    (new ComposerRunner)->run(base_path(), ['dump-autoload']);

    Process::assertRan(function ($process) {
        if (! is_array($process->command)) {
            return false;
        }

        return is_string($process->command[0])
            && $process->command[0] !== ''
            && $process->command[1] === $this->pharPath
            && $process->command[2] === 'dump-autoload';
    });
});

it('falls back to the STRING "composer ..." form when the configured phar does not exist', function () {
    config(['zenon.composer.phar_path' => $this->pharPath]); // never created in this test

    (new ComposerRunner)->run(base_path(), ['dump-autoload']);

    Process::assertRan(function ($process) {
        return $process->command === 'composer dump-autoload';
    });
});

it('uses zenon.composer.php_binary verbatim as the array command\'s first element when configured', function () {
    File::ensureDirectoryExists(dirname($this->pharPath));
    File::put($this->pharPath, 'not a real phar, just needs to exist');

    config([
        'zenon.composer.phar_path' => $this->pharPath,
        'zenon.composer.php_binary' => '/opt/plesk/php/8.3/bin/php',
    ]);

    (new ComposerRunner)->run(base_path(), ['dump-autoload']);

    Process::assertRan(function ($process) {
        return is_array($process->command) && $process->command[0] === '/opt/plesk/php/8.3/bin/php';
    });
});

it('applies the timeout (default 600, or the explicit override) to the underlying process', function () {
    config(['zenon.composer.phar_path' => $this->pharPath]); // fallback string form; timeout applies to both forms alike

    (new ComposerRunner)->run(base_path(), ['dump-autoload']);

    Process::assertRan(fn ($process) => $process->timeout === 600);

    (new ComposerRunner)->run(base_path(), ['dump-autoload'], 120);

    Process::assertRan(fn ($process) => $process->timeout === 120);
});

it('runs in the given working directory', function () {
    config(['zenon.composer.phar_path' => $this->pharPath]);

    (new ComposerRunner)->run(base_path(), ['dump-autoload']);

    Process::assertRan(fn ($process) => $process->path === base_path());
});
