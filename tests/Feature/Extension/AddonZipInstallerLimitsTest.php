<?php

use App\Foundation\Modules\Models\InstalledModule;
use App\Foundation\Modules\ModuleRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
 * Phase 8 Task 10 (Phase 7 carry-forward b): zip-bomb guards for AddonZipInstaller.
 *
 * Two guards under test, both fed by `config('zenon.addon_zip.*')`:
 *  1. A pre-scan (`assertWithinDeclaredLimits()`) that refuses BEFORE any disk write when
 *     the zip's own central-directory metadata (entry count / declared per-entry size /
 *     declared total size) exceeds the configured limits.
 *  2. An AUTHORITATIVE streaming cap in `extractTo()`: central-directory sizes are
 *     forgeable (a crafted zip can under-declare an entry's uncompressed size while the
 *     entry actually decompresses to far more — inflate doesn't consult that metadata,
 *     it just decodes until its own end-of-stream marker), so extraction reads each entry
 *     through `stream_get_contents($stream, $maxEntryBytes + 1)` and refuses the moment
 *     the ACTUAL bytes read exceed the cap, independent of what the pre-scan saw.
 *
 * Fixture builder / config-seam / cleanup patterns mirror
 * tests/Feature/Extension/ModuleInstallZipCommandTest.php; helper names here are
 * deliberately distinct (Limits-suffixed) because the whole suite runs in one PHP
 * process (see phpunit.xml) and top-level function names collide across test files.
 */

beforeEach(function () {
    $this->limitsTarget = storage_path('framework/testing/install-zip-limits-target-'.uniqid());
    $this->limitsZipDir = storage_path('framework/testing/install-zip-limits-source-'.uniqid());
    File::ensureDirectoryExists($this->limitsTarget);
    File::ensureDirectoryExists($this->limitsZipDir);

    config([
        'zenon.thirdparty_path' => $this->limitsTarget,
        'modules.scan.paths' => [
            $this->limitsTarget.'/*',
            base_path('tests/Fixtures/modules/*'),
        ],
    ]);

    app(ModuleRegistry::class)->flush();

    Process::fake();
});

afterEach(function () {
    File::deleteDirectory($this->limitsTarget);
    File::deleteDirectory($this->limitsZipDir);
});

/** Asserts a directory (that must still exist) contains no entries at all. */
function assertNoResidueInLimitsTarget(string $dir): void
{
    expect(is_dir($dir))->toBeTrue();
    expect(array_diff(scandir($dir) ?: [], ['.', '..']))->toBe([]);
}

/**
 * Builds a minimal, otherwise-valid "Limitsdemo" addon zip: module.json + composer.json
 * (psr-4 autoload) + a provider source file (never actually class_exists()-checked by
 * these tests — every scenario below is refused during the pre-scan or the streaming
 * extraction step, both of which run BEFORE dumpAutoload/registerLiveLoader/manager
 * install ever look at the provider class).
 *
 * @param  array<string, string>  $extraEntries  extra zip entry name => contents, added
 *                                               honestly (real content length == what the
 *                                               zip's central directory will declare)
 */
function buildLimitsAddonZip(string $zipPath, array $extraEntries = []): void
{
    $manifest = json_encode([
        'name' => 'Limitsdemo',
        'alias' => 'limitsdemo',
        'providers' => ['Modules\\Limitsdemo\\Providers\\LimitsdemoServiceProvider'],
        'zenon' => [
            'id' => 'acme/limitsdemo',
            'version' => '1.0.0',
            'core' => false,
            'requires' => [],
            'provides' => [],
            'hooks' => ['emits' => []],
            'permissions' => [],
            'frontend' => ['entry' => null, 'remote' => null],
            'platform' => '^1.0',
            'defaultEnabled' => false,
        ],
    ], JSON_THROW_ON_ERROR);

    $composerJson = json_encode([
        'name' => 'acme/limitsdemo',
        'type' => 'zenon-module',
        'autoload' => ['psr-4' => ['Modules\\Limitsdemo\\' => 'app/']],
    ], JSON_THROW_ON_ERROR);

    $provider = <<<'PHP'
        <?php

        namespace Modules\Limitsdemo\Providers;

        use App\Foundation\Modules\ModuleServiceProvider;

        class LimitsdemoServiceProvider extends ModuleServiceProvider
        {
            protected string $name = 'Limitsdemo';

            protected string $nameLower = 'limitsdemo';
        }

        PHP;

    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', $manifest);
    $zip->addFromString('composer.json', $composerJson);
    $zip->addFromString('app/Providers/LimitsdemoServiceProvider.php', $provider);

    foreach ($extraEntries as $entryName => $contents) {
        $zip->addFromString($entryName, $contents);
    }

    $zip->close();
}

/**
 * Binary-patches the CENTRAL DIRECTORY's "uncompressed size" field for a single named
 * entry down to $fakeDeclaredSize, WITHOUT touching that entry's local file header or its
 * actual compressed data. This is the forged-header case from the brief: statIndex()
 * (which the pre-scan reads) reports the small lie; the real decompressed payload
 * (unaffected — inflate doesn't consult this metadata) stays exactly as large as it
 * actually is.
 *
 * Verified empirically (scratch experiments, not shipped) against php-zip/libzip on this
 * box: patching ONLY the central directory's uncompressed-size field is sufficient —
 * ZipArchive::statIndex() reads the (now-lying) central directory value, while
 * ZipArchive::getStream() still decodes the full real DEFLATE payload (bounded by the
 * UNCHANGED compressed-size field / the deflate stream's own end marker, never by the
 * uncompressed-size metadata).
 */
function forgeCentralDirectoryUncompressedSize(string $zipPath, string $entryName, int $fakeDeclaredSize): void
{
    $bytes = file_get_contents($zipPath);

    if ($bytes === false) {
        throw new RuntimeException("Could not read zip [$zipPath] for forging.");
    }

    $cdPos = strpos($bytes, "PK\x01\x02");

    while ($cdPos !== false) {
        $nameLen = unpack('v', substr($bytes, $cdPos + 28, 2))[1];
        $extraLen = unpack('v', substr($bytes, $cdPos + 30, 2))[1];
        $commentLen = unpack('v', substr($bytes, $cdPos + 32, 2))[1];
        $name = substr($bytes, $cdPos + 46, $nameLen);

        if ($name === $entryName) {
            $patched = substr_replace($bytes, pack('V', $fakeDeclaredSize), $cdPos + 24, 4);
            file_put_contents($zipPath, $patched);

            return;
        }

        $cdPos = strpos($bytes, "PK\x01\x02", $cdPos + 46 + $nameLen + $extraLen + $commentLen);
    }

    throw new RuntimeException("Central directory entry [$entryName] not found in [$zipPath] to forge.");
}

it('refuses an oversized single entry (declared honestly) via the pre-scan, before any disk write', function () {
    config([
        'zenon.addon_zip.max_entry_bytes' => 500,
        'zenon.addon_zip.max_total_bytes' => 1_000_000,
        'zenon.addon_zip.max_entries' => 100,
    ]);

    $zipPath = $this->limitsZipDir.'/limitsdemo.zip';
    buildLimitsAddonZip($zipPath, extraEntries: [
        'assets/toolarge.bin' => str_repeat('X', 600),
    ]);

    // A single substring check spanning both tokens: expectsOutputToContain() consumes
    // one matching console write per call, so two overlapping checks against the SAME
    // single-line message would race each other for that one write (see the identical
    // pattern/comment in ModuleInstallZipCommandTest).
    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('assets/toolarge.bin] declares 600 bytes, exceeding the 500-byte limit (zenon.addon_zip.max_entry_bytes)')
        ->assertFailed();

    assertNoResidueInLimitsTarget($this->limitsTarget);
    expect(InstalledModule::query()->where('alias', 'limitsdemo')->exists())->toBeFalse();
    Process::assertNothingRan();
});

it('refuses an oversized declared total via the pre-scan, before any disk write', function () {
    config([
        'zenon.addon_zip.max_entry_bytes' => 1_000_000,
        'zenon.addon_zip.max_total_bytes' => 1_500,
        'zenon.addon_zip.max_entries' => 100,
    ]);

    $zipPath = $this->limitsZipDir.'/limitsdemo.zip';
    buildLimitsAddonZip($zipPath, extraEntries: [
        'assets/one.bin' => str_repeat('A', 800),
        'assets/two.bin' => str_repeat('B', 800),
    ]);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('exceeding the 1500-byte limit (zenon.addon_zip.max_total_bytes)')
        ->assertFailed();

    assertNoResidueInLimitsTarget($this->limitsTarget);
    expect(InstalledModule::query()->where('alias', 'limitsdemo')->exists())->toBeFalse();
    Process::assertNothingRan();
});

it('refuses too many entries via the pre-scan, before any disk write', function () {
    config([
        'zenon.addon_zip.max_entry_bytes' => 1_000_000,
        'zenon.addon_zip.max_total_bytes' => 1_000_000,
        'zenon.addon_zip.max_entries' => 3,
    ]);

    // module.json + composer.json + provider.php already total 3; one more tips it over.
    $zipPath = $this->limitsZipDir.'/limitsdemo.zip';
    buildLimitsAddonZip($zipPath, extraEntries: [
        'assets/one-too-many.txt' => 'x',
    ]);

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('Zip declares 4 entries, exceeding the 3-entry limit (zenon.addon_zip.max_entries)')
        ->assertFailed();

    assertNoResidueInLimitsTarget($this->limitsTarget);
    expect(InstalledModule::query()->where('alias', 'limitsdemo')->exists())->toBeFalse();
    Process::assertNothingRan();
});

it('installs a zip sitting EXACTLY at all three configured limits (boundary)', function () {
    // Build the fixture content first so the limits below can be set to match it exactly.
    $manifest = json_encode([
        'name' => 'Limitsdemo',
        'alias' => 'limitsdemo',
        'providers' => ['Modules\\Limitsdemo\\Providers\\LimitsdemoServiceProvider'],
        'zenon' => [
            'id' => 'acme/limitsdemo',
            'version' => '1.0.0',
            'core' => false,
            'requires' => [],
            'provides' => [],
            'hooks' => ['emits' => []],
            'permissions' => [],
            'frontend' => ['entry' => null, 'remote' => null],
            'platform' => '^1.0',
            'defaultEnabled' => false,
        ],
    ], JSON_THROW_ON_ERROR);

    $composerJson = json_encode([
        'name' => 'acme/limitsdemo',
        'type' => 'zenon-module',
        'autoload' => ['psr-4' => ['Modules\\Limitsdemo\\' => 'app/']],
    ], JSON_THROW_ON_ERROR);

    $provider = <<<'PHP'
        <?php

        namespace Modules\Limitsdemo\Providers;

        use App\Foundation\Modules\ModuleServiceProvider;

        class LimitsdemoServiceProvider extends ModuleServiceProvider
        {
            protected string $name = 'Limitsdemo';

            protected string $nameLower = 'limitsdemo';
        }

        PHP;

    $sizes = [strlen($manifest), strlen($composerJson), strlen($provider)];
    $maxEntryBytes = max($sizes); // exactly the largest of the 3 entries — none may exceed it
    $maxTotalBytes = array_sum($sizes); // exactly the sum — the running total may not exceed it

    config([
        'zenon.addon_zip.max_entry_bytes' => $maxEntryBytes,
        'zenon.addon_zip.max_total_bytes' => $maxTotalBytes,
        'zenon.addon_zip.max_entries' => 3, // exactly the entry count — 3 files, no more
    ]);

    $zipPath = $this->limitsZipDir.'/limitsdemo.zip';
    $zip = new ZipArchive;
    $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('module.json', $manifest);
    $zip->addFromString('composer.json', $composerJson);
    $zip->addFromString('app/Providers/LimitsdemoServiceProvider.php', $provider);
    $zip->close();

    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('Installed [limitsdemo]')
        ->assertSuccessful();

    expect(is_dir($this->limitsTarget.'/Limitsdemo'))->toBeTrue();
    expect(InstalledModule::query()->where('alias', 'limitsdemo')->exists())->toBeTrue();
});

it('refuses a zip whose central directory forges a small declared size for an entry that actually decompresses to far more', function () {
    // Real payload: 5000 highly-compressible bytes (compresses to well under maxEntryBytes
    // on disk) — but we patch the CENTRAL DIRECTORY's uncompressed-size field for this
    // entry down to 10 bytes AFTER building the zip, leaving the actual compressed data
    // (and its true decompressed content) untouched.
    $realPayload = str_repeat('A', 5000);
    $fakeDeclaredSize = 10;

    // maxEntryBytes (1000) sits between the lie (10) and the truth (5000) — and is
    // comfortably above the honest module.json/composer.json/provider.php entries too, so
    // ONLY the forged entry is at issue. The pre-scan — which only ever reads the (forged)
    // central-directory size via statIndex() — sees 10 <= 1000 and PASSES this entry. Only
    // the streaming cap, which reads the ACTUAL bytes via a hard-limited
    // stream_get_contents(), ever observes the real 5000-byte payload.
    config([
        'zenon.addon_zip.max_entry_bytes' => 1_000,
        'zenon.addon_zip.max_total_bytes' => 1_000_000,
        'zenon.addon_zip.max_entries' => 100,
    ]);

    $zipPath = $this->limitsZipDir.'/limitsdemo.zip';
    buildLimitsAddonZip($zipPath, extraEntries: [
        'assets/bomb.bin' => $realPayload,
    ]);

    forgeCentralDirectoryUncompressedSize($zipPath, 'assets/bomb.bin', $fakeDeclaredSize);

    // Sanity-check the forgery actually fooled statIndex() before asserting on the
    // installer's behaviour — if this ever stops holding (e.g. a future libzip trusts the
    // local file header instead), this test must fail LOUDLY here rather than silently
    // degrading into a re-test of the pre-scan.
    $probe = new ZipArchive;
    $probe->open($zipPath);
    $probeStat = $probe->statIndex($probe->locateName('assets/bomb.bin'));
    $probe->close();
    expect($probeStat['size'])->toBe($fakeDeclaredSize);

    // Single combined substring check — see the note above about expectsOutputToContain()
    // consuming one console write per call.
    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain('assets/bomb.bin] streamed more than 1000 bytes, exceeding the declared-safe per-entry limit (zenon.addon_zip.max_entry_bytes)')
        ->assertFailed();

    assertNoResidueInLimitsTarget($this->limitsTarget);
    expect(InstalledModule::query()->where('alias', 'limitsdemo')->exists())->toBeFalse();
    // The failure happens mid-extraction (before moveDirectory), so composer never runs.
    Process::assertNothingRan();
});

it('refuses a zip whose central directory forges tiny declared sizes for several entries whose ACTUAL streamed total exceeds the limit (streaming-total guard)', function () {
    // Streaming-total analogue of the single-entry forged-header test above: three ~800-byte
    // real entries, EACH forged in the central directory down to a tiny declared size (10),
    // so the pre-scan's declared total (~30 bytes from the bombs, plus the honest
    // module.json/composer.json/provider.php sizes) sails through comfortably under
    // max_total_bytes — only the AUTHORITATIVE streaming guard, which sums ACTUAL bytes
    // read via extractTo()'s stream_get_contents(), ever sees the true ~2400-byte bomb
    // total. max_entry_bytes (1000) sits above every real entry (including each 800-byte
    // bomb), so the per-entry cap never trips — only the running-TOTAL guard can catch this.
    $realPayload = str_repeat('A', 800);
    $fakeDeclaredSize = 10;
    $bombNames = ['assets/bomb1.bin', 'assets/bomb2.bin', 'assets/bomb3.bin'];

    config([
        'zenon.addon_zip.max_entry_bytes' => 1_000,
        'zenon.addon_zip.max_total_bytes' => 1_500,
        'zenon.addon_zip.max_entries' => 100,
    ]);

    $zipPath = $this->limitsZipDir.'/limitsdemo.zip';
    buildLimitsAddonZip($zipPath, extraEntries: array_combine($bombNames, array_fill(0, count($bombNames), $realPayload)));

    // Compute the exact ACTUAL running total (and the entry at which it first crosses
    // max_total_bytes) from the REAL, not-yet-forged per-entry declared sizes, in the same
    // order the installer's extractTo() iterates zip entries — dynamic, like the "sitting
    // EXACTLY at all three configured limits" boundary test above, rather than hardcoding
    // the manifest/composer/provider byte counts.
    $probeBeforeForge = new ZipArchive;
    $probeBeforeForge->open($zipPath);
    $runningTotal = 0;
    $expectedTripEntry = null;
    $expectedTripTotal = null;

    for ($i = 0; $i < $probeBeforeForge->numFiles; $i++) {
        $stat = $probeBeforeForge->statIndex($i);
        $runningTotal += $stat['size'];

        if ($expectedTripEntry === null && $runningTotal > 1_500) {
            $expectedTripEntry = $stat['name'];
            $expectedTripTotal = $runningTotal;
        }
    }
    $probeBeforeForge->close();

    // Sanity: the fixture really does exceed 1500 real bytes somewhere before any forging —
    // if this stops holding, the test fixture (not the guard) is wrong.
    expect($expectedTripEntry)->not->toBeNull();

    foreach ($bombNames as $bombName) {
        forgeCentralDirectoryUncompressedSize($zipPath, $bombName, $fakeDeclaredSize);
    }

    // Sanity-check the forgery fooled statIndex() for all three bombs — if this ever stops
    // holding (e.g. a future libzip trusts the local file header instead), this test must
    // fail LOUDLY here rather than silently degrading into a re-test of the pre-scan (same
    // rationale as the single-entry forged-header test above).
    $probeAfterForge = new ZipArchive;
    $probeAfterForge->open($zipPath);

    foreach ($bombNames as $bombName) {
        $stat = $probeAfterForge->statIndex($probeAfterForge->locateName($bombName));
        expect($stat['size'])->toBe($fakeDeclaredSize);
    }

    $probeAfterForge->close();

    // Single combined substring check (see the note above about expectsOutputToContain()
    // consuming one matching console write per call) — asserts the STREAMING-total message
    // specifically: it names the entry AND the accumulated actual bytes, which is exactly
    // what distinguishes it from the pre-scan's "Zip declares a total uncompressed size of
    // at least..." message asserted in the declared-total pre-scan test above.
    $this->artisan('zenon:module:install-zip', ['path' => $zipPath])
        ->expectsOutputToContain(sprintf(
            'Zip entry [%s] pushed the streamed total to %d actual bytes, exceeding the 1500-byte limit (zenon.addon_zip.max_total_bytes)',
            $expectedTripEntry,
            $expectedTripTotal,
        ))
        ->assertFailed();

    assertNoResidueInLimitsTarget($this->limitsTarget);
    expect(InstalledModule::query()->where('alias', 'limitsdemo')->exists())->toBeFalse();
    // The failure happens mid-extraction (before moveDirectory), so composer never runs.
    Process::assertNothingRan();
});
