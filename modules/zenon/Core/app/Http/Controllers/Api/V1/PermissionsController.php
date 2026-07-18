<?php

namespace Modules\Core\Http\Controllers\Api\V1;

use App\Foundation\Api\ApiController;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Core\Http\Resources\PermissionResource;
use Spatie\Permission\Models\Permission;

class PermissionsController extends ApiController
{
    /** Flat, unpaginated list — feeds the role editor's permission picker. */
    public function __invoke(): AnonymousResourceCollection
    {
        return PermissionResource::collection(Permission::query()->orderBy('name')->get());
    }
}
