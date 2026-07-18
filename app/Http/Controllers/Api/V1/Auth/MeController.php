<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // auth:sanctum ran shouldUse('sanctum'), so user() is the session-authenticated
        // tenant user; the instanceof doubles as Larastan narrowing.
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return UserResource::make($user)->response();
    }
}
