<?php

namespace App\Http\Controllers\Api\V1\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\CentralUser;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        abort_unless($request->hasSession(), 400, 'Stateful same-origin request required.');

        $request->authenticate('central');
        $request->session()->regenerate();

        $user = $request->user('central');
        assert($user instanceof CentralUser);

        return UserResource::make($user)->response();
    }
}
