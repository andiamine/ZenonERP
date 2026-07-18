<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Modules\Core\Actions\SyncRolePermissions;
use Modules\Core\Http\Requests\SyncRolePermissionsRequest;
use Modules\Core\Http\Resources\RoleResource;
use Spatie\Permission\Models\Role;

class RolePermissionsController extends ApiController
{
    public function __invoke(SyncRolePermissionsRequest $request, Role $role, SyncRolePermissions $action): RoleResource
    {
        /** @var list<string> $permissions */
        $permissions = $request->validated('permissions');

        $role = $action->handle($role, $permissions);

        return RoleResource::make($role->load('permissions'));
    }
}
