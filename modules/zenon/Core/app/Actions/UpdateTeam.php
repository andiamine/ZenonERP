<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Team;

final class UpdateTeam
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): Team
    {
        $team->update($data);

        return $team;
    }
}
