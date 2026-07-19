<?php

namespace Modules\Core\Actions;

use App\Foundation\Hooks\HookBus;
use Modules\Core\Contracts\Events\CompanyDeleted;
use Modules\Core\Contracts\Hooks\CompanyDeleting;
use Modules\Core\Models\Company;

/**
 * Guards the two invariants a company delete must never violate (CLAUDE.md §9.1): the
 * default company anchors seeding/fallback logic tenant-wide, and a tenant can never be
 * left with zero companies. Invariants are checked first, before the extension veto (§6)
 * gets a say — a filter can abort the request but never override a core data-integrity
 * guard.
 */
final class DeleteCompany
{
    public function __construct(private readonly HookBus $hooks) {}

    public function handle(Company $company): void
    {
        abort_if($company->is_default, 409, 'The default company cannot be deleted.');
        abort_if(Company::query()->count() <= 1, 409, 'The last company cannot be deleted.');

        $this->hooks->filter(new CompanyDeleting($company->id, $company->code, $company->name));

        $company->delete();

        CompanyDeleted::dispatch($company->id, $company->code, $company->name);
    }
}
