<?php

namespace Modules\Sequence\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\Settings\SettingsReader;
use Modules\Sequence\Contracts\SequenceGenerator;
use Modules\Sequence\Contracts\SequenceRegistrar;
use Modules\Sequence\Models\Sequence;
use Modules\Sequence\Support\MaskFormatter;
use RuntimeException;

/**
 * Gapless document numbering (CLAUDE.md §9.2 — Odoo no_gap semantics).
 *
 * The number is allocated AND the row is persisted inside a single DB transaction while
 * the row is held under `lockForUpdate()`. Two guarantees follow:
 *
 *  - Standalone call: the transaction commits immediately, the number is consumed.
 *  - Called inside an outer transaction: this inner transaction becomes a SAVEPOINT and
 *    the row lock is held until the OUTER commit. If the outer work rolls back, the
 *    allocation is undone and the very next call re-issues the same number — true gapless
 *    (the number is only "spent" when the surrounding business write commits).
 *
 * Concurrency correctness rests on real row locks: on MySQL/MariaDB InnoDB, `lockForUpdate`
 * serialises concurrent allocators on the counter row. (Under sqlite FOR UPDATE is a no-op,
 * but sqlite serialises writers wholesale, so the sequential test tier still holds; the
 * MariaDB-gated concurrency test is what actually proves the lock — see the module tests.)
 *
 * Phase 5 implements ONLY the gapless path; the `gapless` column reserves a future
 * fast-path (allocate-outside-transaction) option — documented, not yet implemented.
 */
final class SequenceService implements SequenceGenerator
{
    public function __construct(
        private readonly SequenceRegistrar $registrar,
        private readonly SettingsReader $settings,
    ) {}

    public function next(string $code, ?int $companyId = null): string
    {
        return DB::transaction(function () use ($code, $companyId): string {
            $row = $this->lockedRow($code, $companyId);

            $period = $this->periodFor($row);

            if ($row->reset_period !== 'never' && $row->current_period !== $period) {
                $row->next_number = 1;
                $row->current_period = $period;
            }

            $number = $row->next_number;
            $row->next_number = $number + 1;
            $row->save();

            return MaskFormatter::format($row->mask, $number, $this->companyCode($companyId), $period);
        });
    }

    /**
     * Fetch the counter row under a write lock, materialising it from the registry
     * definition on first use. The create is guarded against a concurrent materialiser:
     * a duplicate-key QueryException means another allocator won the race, so we simply
     * re-select the row it created (now under our lock).
     */
    private function lockedRow(string $code, ?int $companyId): Sequence
    {
        $scope = $companyId ?? 0;

        $row = $this->selectLocked($code, $scope);

        if ($row !== null) {
            return $row;
        }

        $definition = $this->registrar->all()[$code] ?? null;

        try {
            Sequence::query()->create([
                'code' => $code,
                'company_id' => $companyId,
                'mask' => $definition->mask ?? '{seq:5}',
                'reset_period' => $definition->resetPeriod ?? 'never',
                'gapless' => $definition->gapless ?? true,
                'next_number' => 1,
            ]);
        } catch (QueryException) {
            // A racing allocator created the row first — fall through to re-select it.
        }

        return $this->selectLocked($code, $scope)
            ?? throw new RuntimeException("Failed to materialise sequence [{$code}].");
    }

    private function selectLocked(string $code, int $scope): ?Sequence
    {
        return Sequence::query()
            ->where('code', $code)
            ->where('company_scope', $scope)
            ->lockForUpdate()
            ->first();
    }

    /**
     * The period key for reset comparison and the mask date tokens. `never` → null;
     * `month` → 'YYYY-MM' (calendar month); `year` → the fiscal year the current date
     * falls in, honouring core.fiscal_year_start_month (per the sequence's company).
     */
    private function periodFor(Sequence $row): ?string
    {
        if ($row->reset_period === 'never') {
            return null;
        }

        $now = Carbon::now();

        if ($row->reset_period === 'month') {
            return $now->format('Y-m');
        }

        $startMonth = (int) $this->settings->get('core.fiscal_year_start_month', $row->company_id);

        if ($startMonth < 1 || $startMonth > 12) {
            $startMonth = 1;
        }

        $fiscalYear = $now->month >= $startMonth ? $now->year : $now->year - 1;

        return (string) $fiscalYear;
    }

    /**
     * Company code for the {company} mask token, '' when tenant-wide.
     *
     * Reads `companies` directly via the query builder: the cross-module rule bans PHP
     * imports of another module's internals (Modules\Core\Models\Company), not SQL against
     * its table. The FK sequences.company_id → companies already couples the schemas; this
     * read-only lookup adds no new coupling. (CLAUDE.md §9.2 sanctioned exception.)
     */
    private function companyCode(?int $companyId): string
    {
        if ($companyId === null) {
            return '';
        }

        return (string) DB::table('companies')->where('id', $companyId)->value('code');
    }
}
