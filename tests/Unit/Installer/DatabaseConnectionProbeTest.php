<?php

use App\Foundation\Installer\DatabaseConnectionProbe;
use App\Foundation\Installer\ProbeResult;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 8 Task 6. DatabaseConnectionProbe needs a booted app (config()/DB facade), so
 * this binds Tests\TestCase unlike the framework-free EnvWriterTest.
 *
 * Choice on the "fast failure" case (brief explicitly allows either): a bogus-host
 * MySQL probe was considered, but a real TCP connect timeout to an unroutable host is
 * not reliably fast across platforms/CI (Windows in particular can take much longer
 * than the PDO::ATTR_TIMEOUT option to actually abandon a SYN with no response) — an
 * invalid SQLite path (nonexistent parent directory, which PDO's sqlite driver never
 * creates) fails instantly and needs no network at all, so that's what's exercised
 * below. The mysql/mariadb short-timeout branch in DatabaseConnectionProbe itself is
 * still implemented for production robustness; it's just not what this unit test times.
 */
uses(TestCase::class);

afterEach(function () {
    DB::purge('__installer_probe');
});

it('succeeds against a real, reachable sqlite database', function () {
    $path = database_path('phase8_probe_success_test.sqlite');
    file_put_contents($path, '');

    try {
        $result = (new DatabaseConnectionProbe)->probe([
            'driver' => 'sqlite',
            'database' => $path,
            'foreign_key_constraints' => true,
        ]);

        expect($result)->toBeInstanceOf(ProbeResult::class)
            ->and($result->success)->toBeTrue()
            ->and($result->message)->toBeNull();
    } finally {
        @unlink($path);
    }
});

it('fails fast with the driver error message against an invalid sqlite path (nonexistent parent directory)', function () {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zenon_probe_missing_dir_'.uniqid('', true).DIRECTORY_SEPARATOR.'probe.sqlite';

    $result = (new DatabaseConnectionProbe)->probe([
        'driver' => 'sqlite',
        'database' => $path,
        'foreign_key_constraints' => true,
    ]);

    expect($result->success)->toBeFalse()
        ->and($result->message)->toBeString()
        ->and($result->message)->not->toBe('');
});

it('leaves no throwaway connection config behind after a successful probe', function () {
    $path = database_path('phase8_probe_cleanup_test.sqlite');
    file_put_contents($path, '');

    try {
        (new DatabaseConnectionProbe)->probe([
            'driver' => 'sqlite',
            'database' => $path,
            'foreign_key_constraints' => true,
        ]);

        expect(config('database.connections.__installer_probe'))->toBeNull();
    } finally {
        @unlink($path);
    }
});

it('leaves no throwaway connection config behind after a failed probe', function () {
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zenon_probe_missing_dir_'.uniqid('', true).DIRECTORY_SEPARATOR.'probe.sqlite';

    (new DatabaseConnectionProbe)->probe([
        'driver' => 'sqlite',
        'database' => $path,
        'foreign_key_constraints' => true,
    ]);

    expect(config('database.connections.__installer_probe'))->toBeNull();
});
