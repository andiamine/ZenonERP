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
        return Company::query()->create($data);
    }
}
