<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Company;

final class SyncCompanyUsers
{
    /**
     * @param  list<int>  $userIds
     */
    public function handle(Company $company, array $userIds): Company
    {
        $company->users()->sync($userIds);

        return $company;
    }
}
