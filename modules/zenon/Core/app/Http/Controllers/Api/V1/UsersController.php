<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Core\Actions\CreateUser;
use Modules\Core\Actions\DeleteUser;
use Modules\Core\Actions\UpdateUser;
use Modules\Core\Http\Requests\StoreUserRequest;
use Modules\Core\Http\Requests\UpdateUserRequest;
use Modules\Core\Http\Resources\UserResource;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class UsersController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = QueryBuilder::for(User::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::partial('email'),
                AllowedFilter::exact('role', 'roles.name'),
            )
            ->allowedSorts('name', 'email', 'id')
            ->allowedIncludes('roles')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        return UserResource::make($user);
    }

    public function store(StoreUserRequest $request, CreateUser $action): JsonResponse
    {
        $user = $action->handle($request->validated());

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $action): UserResource
    {
        return UserResource::make($action->handle($user, $request->validated()));
    }

    public function destroy(Request $request, User $user, DeleteUser $action): Response
    {
        /** @var User $actingUser */
        $actingUser = $request->user();

        $action->handle($user, $actingUser);

        return $this->noContent();
    }
}
