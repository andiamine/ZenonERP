<?php

namespace Modules\Core\Actions;

use Modules\Core\Models\Team;

final class CreateTeam
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Team
    {
        return Team::query()->create($data);
    }
}
