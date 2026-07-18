<?php

namespace App\Foundation\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opt-in trait for any model carrying a company_id column (CLAUDE.md §9.3).
 *
 * Deliberately does NOT auto-fill company_id on creating — there is no `creating()`
 * hook here. Auto-fill would default every new row to the active company, which would
 * make deliberately-shared NULL rows (master data) impossible to create through normal
 * model usage. Revisit once the first transactional vertical needs company_id to be
 * mandatory (NOT NULL) on create — that module can add its own creating() fill.
 *
 * company_model is read via config('zenon.company_model') (a class-string) rather than
 * imported directly: Foundation must never `use Modules\...` (CLAUDE.md §5).
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function company(): BelongsTo
    {
        /** @var class-string<Model> $companyModel */
        $companyModel = config('zenon.company_model');

        return $this->belongsTo($companyModel, 'company_id');
    }
}
