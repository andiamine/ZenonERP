<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use App\Models\User;
use Modules\Core\Actions\SyncUserRoles;
use Modules\Core\Http\Requests\SyncUserRolesRequest;
use Modules\Core\Http\Resources\UserResource;

class UserRolesController extends ApiController
{
    public function __invoke(SyncUserRolesRequest $request, User $user, SyncUserRoles $action): UserResource
    {
        /** @var list<string> $roles */
        $roles = $request->validated('roles');

        $user = $action->handle($user, $roles);

        return UserResource::make($user->load('roles'));
    }
}
