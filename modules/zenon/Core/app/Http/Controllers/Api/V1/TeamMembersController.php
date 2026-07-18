<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Modules\Core\Actions\SyncTeamMembers;
use Modules\Core\Http\Requests\SyncTeamMembersRequest;
use Modules\Core\Http\Resources\TeamResource;
use Modules\Core\Models\Team;

class TeamMembersController extends ApiController
{
    public function __invoke(SyncTeamMembersRequest $request, Team $team, SyncTeamMembers $action): TeamResource
    {
        /** @var list<int> $userIds */
        $userIds = $request->validated('user_ids');

        $team = $action->handle($team, $userIds);

        return TeamResource::make($team->load('users'));
    }
}
