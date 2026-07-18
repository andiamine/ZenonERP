<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Core\Actions\CreateTeam;
use Modules\Core\Actions\DeleteTeam;
use Modules\Core\Actions\UpdateTeam;
use Modules\Core\Http\Requests\StoreTeamRequest;
use Modules\Core\Http\Requests\UpdateTeamRequest;
use Modules\Core\Http\Resources\TeamResource;
use Modules\Core\Models\Team;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TeamsController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $teams = QueryBuilder::for(Team::class)
            ->allowedFilters(
                AllowedFilter::partial('name'),
                AllowedFilter::exact('company_id'),
            )
            ->allowedSorts('name', 'id')
            ->allowedIncludes('users')
            ->paginate($this->perPage($request))
            ->appends($request->query());

        return TeamResource::collection($teams);
    }

    public function show(Team $team): TeamResource
    {
        return TeamResource::make($team);
    }

    public function store(StoreTeamRequest $request, CreateTeam $action): JsonResponse
    {
        $team = $action->handle($request->validated());

        return TeamResource::make($team)->response()->setStatusCode(201);
    }

    public function update(UpdateTeamRequest $request, Team $team, UpdateTeam $action): TeamResource
    {
        return TeamResource::make($action->handle($team, $request->validated()));
    }

    public function destroy(Team $team, DeleteTeam $action): Response
    {
        $action->handle($team);

        return $this->noContent();
    }
}
