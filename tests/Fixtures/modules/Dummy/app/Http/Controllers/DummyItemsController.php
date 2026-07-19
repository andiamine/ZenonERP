<?php

namespace Modules\Dummy\Http\Controllers;

use App\Foundation\Hooks\HookBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Dummy\Contracts\Hooks\DummyItemsApiResponse;

class DummyItemsController
{
    public function __invoke(HookBus $hooks): JsonResponse
    {
        // Phase 6 filter-hook flow: extension modules append computed fields.
        $payload = $hooks->filter(new DummyItemsApiResponse);

        return response()->json([
            'data' => DB::table('dummy_items')->orderBy('id')->get(),
            'extra' => (object) $payload->extra,
        ]);
    }
}
