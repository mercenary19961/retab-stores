<?php

use App\Http\Controllers\Admin\OrderController;
use Illuminate\Support\Facades\Route;

// Back-office (EN-first). Staff only — admin or editor.
Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order:order_number}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
    Route::post('orders/{order:order_number}/unavailable', [OrderController::class, 'markUnavailable'])->name('orders.unavailable');
    Route::post('orders/{order:order_number}/ship', [OrderController::class, 'ship'])->name('orders.ship');
    Route::post('orders/{order:order_number}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
});
