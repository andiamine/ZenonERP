<?php

namespace App\Http\Controllers\Api\V1;

use App\Foundation\Tenancy\Actions\CreateTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SignupRequest;
use App\Http\Resources\Api\V1\TenantResource;
use Illuminate\Http\JsonResponse;

class SignupController extends Controller
{
    public function __invoke(SignupRequest $request, CreateTenant $createTenant): JsonResponse
    {
        // TODO(Phase 3): full §8 error envelope via the single exception renderer.
        $tenant = $createTenant->handle(
            subdomain: $request->string('subdomain')->value(),
            name: $request->filled('name') ? $request->string('name')->value() : null,
        );

        return TenantResource::make($tenant->load('domains'))
            ->response()
            ->setStatusCode(201);
    }
}
