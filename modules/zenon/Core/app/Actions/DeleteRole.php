<?php

namespace Modules\Core\Actions;

use Spatie\Permission\Models\Role;

/** See UpdateRole's docblock — same admin-role invariant. */
final class DeleteRole
{
    public function handle(Role $role): void
    {
        abort_if($role->name === 'admin', 409, 'The admin role cannot be deleted.');

        $role->delete();
    }
}
