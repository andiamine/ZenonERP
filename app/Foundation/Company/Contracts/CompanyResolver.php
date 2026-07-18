<?php

namespace App\Foundation\Company\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The port SetCurrentCompany depends on (CLAUDE.md §8) — implemented and bound by the
 * zenon/core module (a later Phase 5 task) against the companies/company_user tables.
 * Lives in Foundation, not Modules\Core\Contracts, because Foundation middleware must
 * depend on it without importing Modules\* (CLAUDE.md §5).
 */
interface CompanyResolver
{
    /**
     * @return list<int> company ids the user may act as (company switcher, header validation)
     */
    public function companyIdsFor(Authenticatable $user): array;

    /** Null only when the user has no company assignment at all (unscoped fallback). */
    public function defaultCompanyIdFor(Authenticatable $user): ?int;
}
