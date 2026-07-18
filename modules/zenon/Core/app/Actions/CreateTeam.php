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
        $team = Team::query()->create($data);

        // See CreateCompany's docblock: `active` is optional on the request, so an
        // omitted value only gets the schema DEFAULT at the DB layer — refresh so the
        // returned model (and TeamResource) reflects it, not null.
        return $team->refresh();
    }
}
