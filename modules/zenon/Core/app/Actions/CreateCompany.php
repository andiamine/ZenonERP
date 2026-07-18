<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Company;

final class CreateCompany
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Company
    {
        $company = Company::query()->create($data);

        // Refresh: StoreCompanyRequest never accepts `is_default`/`active`, so when they're
        // absent the schema DEFAULT applies at the DB layer only — the in-memory model from
        // create() still has those keys unset (not false/true), which would render as null
        // in CompanyResource. Reloading picks up what the database actually persisted.
        return $company->refresh();
    }
}
