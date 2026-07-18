<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Team;

final class DeleteTeam
{
    public function handle(Team $team): void
    {
        $team->delete();
    }
}
