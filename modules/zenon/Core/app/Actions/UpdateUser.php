<?php

namespace Modules\Core\Actions;

use App\Models\User;

/**
 * Password stays untouched unless the caller explicitly sent one — same `hashed` cast
 * reasoning as CreateUser.
 */
final class UpdateUser
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data): User
    {
        $user->fill(collect($data)->except('password')->all());

        if (array_key_exists('password', $data) && $data['password'] !== null) {
            $user->password = $data['password'];
        }

        $user->save();

        return $user;
    }
}
