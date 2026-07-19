<?php

use Illuminate\Support\Facades\Route;
use Modules\Dummy\Http\Controllers\DummyItemConfirmController;
use Modules\Dummy\Http\Controllers\DummyItemsController;

// Wrapped by the Foundation base provider: /api/v1/dummy/*, gated by module.enabled:dummy.
Route::get('/items', DummyItemsController::class)->name('items');
Route::post('/items/confirm', DummyItemConfirmController::class)->name('items.confirm');
