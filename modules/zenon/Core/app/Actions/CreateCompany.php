<?php

namespace Modules\Core\Actions;

use App\Models\User;
use Modules\Core\Models\Company;

final class CreateCompany
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $creator): Company
    {
        $company = Company::query()->create($data);

        // Attach the creating user so the new company shows up in their membership-filtered
        // bootstrap `companies` (and the switcher) — without this a freshly created company
        // is invisible to its own creator. syncWithoutDetaching is idempotent on retries.
        $company->users()->syncWithoutDetaching([$creator->getKey()]);

        // Refresh: StoreCompanyRequest never accepts `is_default`/`active`, so when they're
        // absent the schema DEFAULT applies at the DB layer only — the in-memory model from
        // create() still has those keys unset (not false/true), which would render as null
        // in CompanyResource. Reloading picks up what the database actually persisted.
        return $company->refresh();
    }
}
