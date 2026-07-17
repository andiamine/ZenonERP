<?php

use Illuminate\Support\Facades\Route;
use Modules\Dummy\Http\Controllers\DummyItemsController;

// Wrapped by the Foundation base provider: /api/v1/dummy/items, gated by module.enabled:dummy.
Route::get('/items', DummyItemsController::class)->name('items');
