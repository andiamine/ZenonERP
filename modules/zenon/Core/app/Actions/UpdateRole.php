<?php

namespace Modules\Core\Actions;

use Spatie\Permission\Models\Role;

/**
 * The `admin` role name is hardcoded in CoreServiceProvider's Gate::before super-user
 * check — renaming it away would silently strip every admin of their bypass.
 */
final class UpdateRole
{
    /**
     * @param  array{name?: string}  $data
     */
    public function handle(Role $role, array $data): Role
    {
        if ($role->name === 'admin' && array_key_exists('name', $data) && $data['name'] !== 'admin') {
            abort(409, 'The admin role cannot be renamed.');
        }

        $role->update(['name' => $data['name'] ?? $role->name]);

        return $role;
    }
}
