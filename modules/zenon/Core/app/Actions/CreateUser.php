<?php

namespace Modules\Core\Actions;

use App\Models\User;
use Modules\Core\Models\Company;

/**
 * Password hashing is deliberately NOT done here: User::$casts hashes `password`
 * automatically on set (Hash::isHashed() guards against double-hashing), so assigning
 * the plain value through create() is the correct, idiomatic call.
 */
final class CreateUser
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $defaultCompany = Company::query()->where('is_default', true)->first();
        $defaultCompany?->users()->attach($user->getKey());

        return $user;
    }
}
