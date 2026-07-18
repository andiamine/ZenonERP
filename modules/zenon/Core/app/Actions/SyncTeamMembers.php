<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Team;

final class SyncTeamMembers
{
    /**
     * @param  list<int>  $userIds
     */
    public function handle(Team $team, array $userIds): Team
    {
        $team->users()->sync($userIds);

        return $team;
    }
}
