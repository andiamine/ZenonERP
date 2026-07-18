<?php

namespace Modules\Core\Actions;

use App\Models\User;

final class DeleteUser
{
    public function handle(User $user, User $actingUser): void
    {
        abort_if($user->is($actingUser), 409, 'You cannot delete your own account.');

        $user->delete();
    }
}
