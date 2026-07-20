<?php

use App\Foundation\Tenancy\CentralDomains;
use Tests\TestCase;

uses(TestCase::class);

it('preserves the pre-Phase-8 hardcoded central domains list by default', function () {
    // TENANCY_CENTRAL_DOMAINS is unset in the test environment (phpunit.xml) — this
    // asserts the env-driven config/tenancy.php still resolves to the exact same list
    // as the hardcoded array it replaces, so no existing test changes behavior.
    expect(config('tenancy.central_domains'))->toBe([
        'zenonerp.test',
        'app.zenonerp.test',
        'localhost',
        '127.0.0.1',
    ]);
});

it('parses a comma-separated env value, trimming whitespace', function () {
    expect(CentralDomains::parse('foo.test, bar.test , baz.test', ['fallback']))
        ->toBe(['foo.test', 'bar.test', 'baz.test']);
});

it('filters empty entries produced by stray or trailing commas', function () {
    expect(CentralDomains::parse('foo.test,,bar.test,', ['fallback']))
        ->toBe(['foo.test', 'bar.test']);
});

it('falls back to the default list only when the env value is genuinely unset (null)', function () {
    expect(CentralDomains::parse(null, ['a', 'b']))->toBe(['a', 'b']);
});

// Standalone mode (CLAUDE.md §7, Phase 8) relies on an EXPLICITLY blank
// TENANCY_CENTRAL_DOMAINS resolving to an empty list — never the default — so that
// routes/api.php's central-domain foreach registers zero central routes. A present-but-
// blank env value is a deliberate instruction, not "unset".
it('resolves to an empty list — NOT the default — when the env value is explicitly blank', function () {
    expect(CentralDomains::parse('', ['a', 'b']))->toBe([])
        ->and(CentralDomains::parse('   ', ['a', 'b']))->toBe([])
        ->and(CentralDomains::parse(' , , ', ['a', 'b']))->toBe([]);
});
