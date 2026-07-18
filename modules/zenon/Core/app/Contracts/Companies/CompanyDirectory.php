<?php

namespace Modules\Core\Contracts\Companies;

use App\Models\User;

interface CompanyDirectory
{
    /**
     * @return list<CompanyData> ordered is_default DESC, id ASC
     */
    public function companiesFor(User $user): array;

    public function defaultCompanyIdFor(User $user): ?int;
}
