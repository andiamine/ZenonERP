<?php

namespace App\Foundation\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Thin base for module API controllers (CLAUDE.md §8). Deliberately no envelope
 * helpers here — API Resources ARE the success envelope ({ data, meta, links });
 * only cross-cutting request-shaping utilities belong on this class.
 */
abstract class ApiController extends Controller
{
    /** Clamps `?per_page` to [1, $max]; falls back to $default when absent or invalid. */
    protected function perPage(Request $request, int $default = 25, int $max = 100): int
    {
        $perPage = $request->integer('per_page');

        if ($perPage < 1) {
            return $default;
        }

        return min($perPage, $max);
    }

    protected function noContent(): Response
    {
        return response()->noContent();
    }
}
