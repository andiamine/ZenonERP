<?php

namespace Modules\Dummy\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DummyItemsController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => DB::table('dummy_items')->orderBy('id')->get(),
        ]);
    }
}
