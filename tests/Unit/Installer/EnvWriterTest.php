<?php

use App\Foundation\Installer\EnvWriter;

/**
 * Phase 8 Task 6: pure unit coverage for EnvWriter against real temp files — no
 * framework boot needed (the class touches no facades/config), so this file uses
 * plain PHP fixtures rather than Laravel's TestCase.
 */
function envWriterTempPath(): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'zenon_installer_envwriter_'.uniqid('', true).'.env';
}

afterEach(function () {
    if (isset($this->path)) {
        @unlink($this->path);
        @unlink($this->path.'.tmp');
    }
});

it('appends new keys to a fresh (nonexistent) file, in the given order', function () {
    $this->path = envWriterTempPath();

    (new EnvWriter)->write($this->path, ['APP_KEY' => 'base64:abc', 'APP_ENV' => 'production']);

    expect(file_get_contents($this->path))->toBe("APP_KEY=base64:abc\nAPP_ENV=production\n");
});

it('replaces an existing key in place, preserving unrelated lines, comments, blank lines, and order', function () {
    $this->path = envWriterTempPath();
    file_put_contents($this->path, "APP_NAME=Laravel\n# a comment\nAPP_ENV=local\n\nDB_CONNECTION=sqlite\n");

    (new EnvWriter)->write($this->path, ['APP_ENV' => 'production', 'NEW_KEY' => 'value']);

    expect(file_get_contents($this->path))->toBe(
        "APP_NAME=Laravel\n# a comment\nAPP_ENV=production\n\nDB_CONNECTION=sqlite\nNEW_KEY=value\n"
    );
});

it('leaves a commented-out key alone and appends the real key instead of uncommenting it', function () {
    $this->path = envWriterTempPath();
    file_put_contents($this->path, "# APP_KEY=old\n");

    (new EnvWriter)->write($this->path, ['APP_KEY' => 'base64:new']);

    expect(file_get_contents($this->path))->toBe("# APP_KEY=old\nAPP_KEY=base64:new\n");
});

it('quotes values containing spaces, #, double quotes, or $; leaves plain values and blanks unquoted', function () {
    $this->path = envWriterTempPath();

    (new EnvWriter)->write($this->path, [
        'APP_NAME' => 'My Company ERP',
        'HASH_TAG' => 'a#b',
        'QUOTE' => 'say "hi"',
        'DOLLAR' => 'a$b',
        'PLAIN' => 'plainvalue',
        'BLANK' => '',
    ]);

    $lines = explode("\n", trim((string) file_get_contents($this->path)));

    expect($lines)->toBe([
        'APP_NAME="My Company ERP"',
        'HASH_TAG="a#b"',
        'QUOTE="say \"hi\""',
        'DOLLAR="a$b"',
        'PLAIN=plainvalue',
        'BLANK=',
    ]);
});

it('writes atomically: no leftover .tmp file after write, and the real file lands with the content', function () {
    $this->path = envWriterTempPath();

    (new EnvWriter)->write($this->path, ['APP_KEY' => 'base64:abc']);

    expect(file_exists($this->path.'.tmp'))->toBeFalse()
        ->and(file_exists($this->path))->toBeTrue()
        ->and(file_get_contents($this->path))->toBe("APP_KEY=base64:abc\n");
});

it('creates the destination directory when it does not exist yet', function () {
    $this->path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zenon_installer_envwriter_dir_'.uniqid('', true).DIRECTORY_SEPARATOR.'.env';

    (new EnvWriter)->write($this->path, ['APP_KEY' => 'base64:abc']);

    expect(file_get_contents($this->path))->toBe("APP_KEY=base64:abc\n");

    @unlink($this->path);
    @rmdir(dirname($this->path));
});

it('round-trips written values (including quoted ones) through read()', function () {
    $this->path = envWriterTempPath();

    (new EnvWriter)->write($this->path, [
        'APP_NAME' => 'My Company ERP',
        'APP_DEBUG' => 'false',
        'TENANCY_CENTRAL_DOMAINS' => '',
    ]);

    expect((new EnvWriter)->read($this->path))->toBe([
        'APP_NAME' => 'My Company ERP',
        'APP_DEBUG' => 'false',
        'TENANCY_CENTRAL_DOMAINS' => '',
    ]);
});

it('read() returns an empty array for a nonexistent file', function () {
    expect((new EnvWriter)->read(envWriterTempPath()))->toBe([]);
});
