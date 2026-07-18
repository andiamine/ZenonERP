<?php

use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Settings\SettingsRepository;
use Modules\Sequence\Contracts\SequenceDefinition;
use Modules\Sequence\Contracts\SequenceGenerator;
use Modules\Sequence\Models\Sequence;
use Modules\Sequence\Services\SequenceRegistry;

/**
 * Boots a tenant with zenon/core + zenon/sequence installed and enabled. installModule
 * auto-installs core (dependency); enableModule auto-enables core then sequence in topo
 * order. Shared across the Sequence suite (mirrors Core's bootCoreTenant).
 */
function bootSequenceTenant(string $subdomain = 'acme'): Tenant
{
    $tenant = createTenant($subdomain);
    installModule('sequence');
    enableModule('sequence', $tenant);

    return $tenant;
}

afterEach(function () {
    Carbon::setTestNow();
});

it('allocates 100 sequential numbers with no gaps or duplicates', function () {
    $tenant = bootSequenceTenant();

    $tenant->run(function () {
        Sequence::query()->create(['code' => 'so', 'mask' => '{seq}']);
        $generator = app(SequenceGenerator::class);

        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $generator->next('so');
        }

        expect($results)->toBe(array_map('strval', range(1, 100)))
            ->and(array_unique($results))->toHaveCount(100)
            ->and(Sequence::query()->where('code', 'so')->value('next_number'))->toBe(101);
    });
});

it('returns the number on rollback — the next call re-issues it (true gapless)', function () {
    $tenant = bootSequenceTenant();

    $tenant->run(function () {
        Sequence::query()->create(['code' => 'so', 'mask' => '{seq}']);
        $generator = app(SequenceGenerator::class);

        expect($generator->next('so'))->toBe('1');

        // Allocate 2 inside an outer transaction, then roll the whole thing back.
        try {
            DB::transaction(function () use ($generator) {
                expect($generator->next('so'))->toBe('2');
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        // 2 was never committed → it is re-issued, not skipped to 3.
        expect($generator->next('so'))->toBe('2')
            ->and($generator->next('so'))->toBe('3');
    });
});

it('restarts the counter at 1 when the fiscal year rolls over (reset_period=year)', function () {
    $tenant = bootSequenceTenant();

    $tenant->run(function () {
        Carbon::setTestNow('2026-06-15');
        Sequence::query()->create(['code' => 'so', 'mask' => '{year}-{seq}', 'reset_period' => 'year']);
        $generator = app(SequenceGenerator::class);

        expect($generator->next('so'))->toBe('2026-1')
            ->and($generator->next('so'))->toBe('2026-2');

        Carbon::setTestNow('2027-01-05');

        expect($generator->next('so'))->toBe('2027-1'); // reset + new year token
    });
});

it('restarts the counter when the calendar month rolls over (reset_period=month)', function () {
    $tenant = bootSequenceTenant();

    $tenant->run(function () {
        Carbon::setTestNow('2026-06-15');
        Sequence::query()->create(['code' => 'mo', 'mask' => '{year}-{month}-{seq}', 'reset_period' => 'month']);
        $generator = app(SequenceGenerator::class);

        expect($generator->next('mo'))->toBe('2026-06-1')
            ->and($generator->next('mo'))->toBe('2026-06-2');

        Carbon::setTestNow('2026-07-01');

        expect($generator->next('mo'))->toBe('2026-07-1');
    });
});

it('honours core.fiscal_year_start_month when computing the {year} token', function () {
    $tenant = bootSequenceTenant();

    $tenant->run(function () {
        // Fiscal year starts in April → 2026-02-15 falls in fiscal year 2025.
        app(SettingsRepository::class)->set('core.fiscal_year_start_month', 4);
        Carbon::setTestNow('2026-02-15');

        Sequence::query()->create(['code' => 'fy', 'mask' => '{year}-{seq}', 'reset_period' => 'year']);

        expect(app(SequenceGenerator::class)->next('fy'))->toBe('2025-1');
    });
});

it('keeps per-company counters independent and renders each company code', function () {
    $tenant = bootSequenceTenant();

    $tenant->run(function () {
        $mainId = (int) DB::table('companies')->where('is_default', true)->value('id'); // code MAIN
        $betaId = DB::table('companies')->insertGetId([
            'name' => 'Beta Co', 'code' => 'BETA', 'currency_code' => 'USD',
            'is_default' => false, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Sequence::query()->create(['code' => 'doc', 'company_id' => $mainId, 'mask' => '{company}-{seq}']);
        Sequence::query()->create(['code' => 'doc', 'company_id' => $betaId, 'mask' => '{company}-{seq}']);

        $generator = app(SequenceGenerator::class);

        expect($generator->next('doc', $mainId))->toBe('MAIN-1')
            ->and($generator->next('doc', $betaId))->toBe('BETA-1')
            ->and($generator->next('doc', $mainId))->toBe('MAIN-2')
            ->and($generator->next('doc', $betaId))->toBe('BETA-2');

        // Two rows, one per company scope — the tenant-wide scope (0) is untouched.
        expect(Sequence::query()->where('code', 'doc')->count())->toBe(2);
    });
});

it('materialises a registered code exactly once across immediate calls', function () {
    $tenant = bootSequenceTenant();

    $tenant->run(function () {
        app(SequenceRegistry::class)->define(new SequenceDefinition('inv', '{seq}'));
        $generator = app(SequenceGenerator::class);

        expect($generator->next('inv'))->toBe('1')
            ->and($generator->next('inv'))->toBe('2')
            ->and(Sequence::query()->where('code', 'inv')->count())->toBe(1);
    });
});

it('falls back to the bare {seq:5} default for an unregistered code', function () {
    $tenant = bootSequenceTenant();

    $tenant->run(function () {
        $generator = app(SequenceGenerator::class);

        expect($generator->next('adhoc'))->toBe('00001')
            ->and($generator->next('adhoc'))->toBe('00002');

        $row = Sequence::query()->where('code', 'adhoc')->firstOrFail();
        expect($row->mask)->toBe('{seq:5}')
            ->and($row->company_scope)->toBe(0); // tenant-wide invariant
    });
});
