<?php

namespace Modules\Core\Actions;

use Spatie\Permission\Models\Role;

final class CreateRole
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Role
    {
        return Role::query()->create(['name' => $data['name'], 'guard_name' => 'web']);
    }
}
