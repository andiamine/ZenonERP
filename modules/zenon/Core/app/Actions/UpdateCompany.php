<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Company;

final class UpdateCompany
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Company $company, array $data): Company
    {
        $company->update($data);

        return $company;
    }
}
