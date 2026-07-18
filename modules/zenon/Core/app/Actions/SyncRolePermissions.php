<?php

namespace Modules\Core\Actions;

use Spatie\Permission\Models\Role;

final class SyncRolePermissions
{
    /**
     * @param  list<string>  $permissions
     */
    public function handle(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);

        return $role;
    }
}
