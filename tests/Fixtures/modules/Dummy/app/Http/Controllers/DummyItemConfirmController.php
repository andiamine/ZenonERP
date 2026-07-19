<?php

namespace Modules\Dummy\Http\Controllers;

use App\Foundation\Hooks\HookBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dummy\Contracts\Events\DummyItemConfirmed;
use Modules\Dummy\Contracts\Hooks\DummyItemConfirming;

/**
 * Probe endpoint for the Phase 6 acceptance flow: vetoable filter before the action
 * commits (an ActionVetoedException propagates to the 422 envelope), then the public
 * event for cross-module listeners.
 */
class DummyItemConfirmController
{
    public function __invoke(Request $request, HookBus $hooks): JsonResponse
    {
        $name = (string) $request->string('name');

        $hooks->filter(new DummyItemConfirming($name));

        DummyItemConfirmed::dispatch($name);

        return response()->json(['data' => ['confirmed' => $name]]);
    }
}
