<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Core\Actions\CreateRole;
use Modules\Core\Actions\DeleteRole;
use Modules\Core\Actions\UpdateRole;
use Modules\Core\Http\Requests\StoreRoleRequest;
use Modules\Core\Http\Requests\UpdateRoleRequest;
use Modules\Core\Http\Resources\RoleResource;
use Spatie\Permission\Models\Role;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class RolesController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $roles = QueryBuilder::for(Role::class)
            ->allowedFilters(AllowedFilter::partial('name'))
            ->allowedSorts('name', 'id')
            ->allowedIncludes('permissions')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return RoleResource::collection($roles);
    }

    public function show(Role $role): RoleResource
    {
        return RoleResource::make($role);
    }

    public function store(StoreRoleRequest $request, CreateRole $action): JsonResponse
    {
        $role = $action->handle($request->validated());

        return RoleResource::make($role)->response()->setStatusCode(201);
    }

    public function update(UpdateRoleRequest $request, Role $role, UpdateRole $action): RoleResource
    {
        return RoleResource::make($action->handle($role, $request->validated()));
    }

    public function destroy(Role $role, DeleteRole $action): Response
    {
        $action->handle($role);

        return $this->noContent();
    }
}
