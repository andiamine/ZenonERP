<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $tenant = tenant();
        abort_if($tenant === null, 500); // unreachable behind InitializeTenancyBySubdomain

        return response()->json([
            'data' => [
                'tenant' => $tenant->getTenantKey(),
                'database' => DB::connection()->getDatabaseName(), // the tenant connection — proof of isolation
            ],
        ]);
    }
}
