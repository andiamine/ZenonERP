<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Serves the SPA shell (the only Blade view) on every non-reserved GET path, on
 * both central and tenant hosts (CLAUDE.md §3). The React shell decides what to
 * render from the /api/v1/bootstrap response.
 */
class SpaController extends Controller
{
    public function __invoke(): View
    {
        return view('app');
    }
}
