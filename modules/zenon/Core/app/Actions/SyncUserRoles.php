<?php

namespace Modules\Core\Actions;

use App\Models\User;

final class SyncUserRoles
{
    /**
     * @param  list<string>  $roles
     */
    public function handle(User $user, array $roles): User
    {
        $user->syncRoles($roles);

        return $user;
    }
}
