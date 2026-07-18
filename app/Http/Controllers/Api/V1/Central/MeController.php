<?php

namespace App\Http\Controllers\Api\V1\Central;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\CentralUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // auth:central ran shouldUse('central'), so user() is the platform operator;
        // the instanceof doubles as Larastan narrowing.
        $user = $request->user();
        abort_unless($user instanceof CentralUser, 401);

        return UserResource::make($user)->response();
    }
}
