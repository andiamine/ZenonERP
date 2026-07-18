<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        // Non-stateful requests (no matching Referer/Origin) never get a session started,
        // so cookie login is impossible for them — fail loud instead of 500 on session().
        abort_unless($request->hasSession(), 400, 'Stateful same-origin request required.');

        $request->authenticate('web');
        $request->session()->regenerate();

        $user = $request->user('web');
        assert($user instanceof User);

        return UserResource::make($user)->response();
    }
}
