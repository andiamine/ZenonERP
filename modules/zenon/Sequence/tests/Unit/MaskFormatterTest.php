<?php

use Illuminate\Support\Carbon;
use Modules\Sequence\Support\MaskFormatter;

afterEach(function () {
    Carbon::setTestNow();
});

it('renders the raw {seq} counter without padding', function () {
    expect(MaskFormatter::format('SO-{seq}', 42, '', null))->toBe('SO-42');
});

it('zero-pads {seq:N} to the requested width', function () {
    expect(MaskFormatter::format('{seq:5}', 42, '', null))->toBe('00042')
        ->and(MaskFormatter::format('{seq:3}', 7, '', null))->toBe('007')
        // a number wider than the pad is emitted in full, never truncated
        ->and(MaskFormatter::format('{seq:2}', 12345, '', null))->toBe('12345');
});

it('renders {year} and {year2} from the period string', function () {
    expect(MaskFormatter::format('{year}-{seq}', 1, '', '2026'))->toBe('2026-1')
        ->and(MaskFormatter::format('{year2}', 1, '', '2026'))->toBe('26');
});

it('renders {month} from a YYYY-MM period', function () {
    expect(MaskFormatter::format('{year}-{month}', 1, '', '2026-07'))->toBe('2026-07');
});

it('falls back to the current calendar date for date tokens when period is null', function () {
    Carbon::setTestNow('2029-03-15');

    expect(MaskFormatter::format('{year}-{month}-{seq}', 9, '', null))->toBe('2029-03-9')
        ->and(MaskFormatter::format('{year2}', 1, '', null))->toBe('29');
});

it('renders {company} with the supplied code, and empty for tenant-wide', function () {
    expect(MaskFormatter::format('{company}-{seq:4}', 5, 'ACME', null))->toBe('ACME-0005')
        ->and(MaskFormatter::format('{company}-{seq:4}', 5, '', null))->toBe('-0005');
});

it('leaves unknown tokens literal', function () {
    expect(MaskFormatter::format('{seq}-{foo}-{bar:3}', 1, '', null))->toBe('1-{foo}-{bar:3}');
});

it('composes a full real-world mask', function () {
    expect(MaskFormatter::format('INV-{company}-{year}-{seq:5}', 128, 'MAIN', '2026'))
        ->toBe('INV-MAIN-2026-00128');
});
