<?php

namespace Modules\Sequence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Numbering counter for one (code, company) pair (CLAUDE.md §9.2).
 *
 * Deliberately NOT company-scoped with a global CompanyScope: Services\SequenceService
 * resolves the company explicitly (a tenant-wide sequence is company_id = NULL / scope 0,
 * and must remain addressable even while a company context is active). A global scope
 * would silently hide those rows and break tenant-wide numbering.
 *
 * The `company_scope` column is a derived shadow of `company_id` (NULL folded to 0) that
 * turns unique(code, company_scope) into a real constraint — it is maintained ONLY here,
 * so no caller ever touches the unique path with a raw company_id.
 *
 * @property int $id
 * @property string $code
 * @property int|null $company_id
 * @property int $company_scope
 * @property string $mask
 * @property int $next_number
 * @property string $reset_period
 * @property string|null $current_period
 * @property bool $gapless
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Sequence extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code', 'company_id', 'mask', 'next_number', 'reset_period', 'current_period', 'gapless',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'company_scope' => 'integer',
            'next_number' => 'integer',
            'gapless' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Single authority for the unique-key invariant: every write (create OR update)
        // recomputes company_scope from company_id so the two can never diverge.
        static::saving(function (Sequence $sequence): void {
            $sequence->company_scope = $sequence->company_id ?? 0;
        });
    }
}
