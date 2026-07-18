<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Company;

/**
 * Guards the two invariants a company delete must never violate (CLAUDE.md §9.1): the
 * default company anchors seeding/fallback logic tenant-wide, and a tenant can never be
 * left with zero companies.
 */
final class DeleteCompany
{
    public function handle(Company $company): void
    {
        abort_if($company->is_default, 409, 'The default company cannot be deleted.');
        abort_if(Company::query()->count() <= 1, 409, 'The last company cannot be deleted.');

        $company->delete();
    }
}
