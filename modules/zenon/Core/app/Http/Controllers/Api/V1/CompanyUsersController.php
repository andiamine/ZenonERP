<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Modules\Core\Actions\SyncCompanyUsers;
use Modules\Core\Http\Requests\SyncCompanyUsersRequest;
use Modules\Core\Http\Resources\CompanyResource;
use Modules\Core\Models\Company;

class CompanyUsersController extends ApiController
{
    public function __invoke(SyncCompanyUsersRequest $request, Company $company, SyncCompanyUsers $action): CompanyResource
    {
        /** @var list<int> $userIds */
        $userIds = $request->validated('user_ids');

        $company = $action->handle($company, $userIds);

        return CompanyResource::make($company);
    }
}
