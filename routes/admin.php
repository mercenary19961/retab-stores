<?php

use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\StockImportController;
use Illuminate\Support\Facades\Route;

// Back-office (EN-first). Staff only — admin or editor.
Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order:order_number}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
    Route::post('orders/{order:order_number}/unavailable', [OrderController::class, 'markUnavailable'])->name('orders.unavailable');
    Route::post('orders/{order:order_number}/ship', [OrderController::class, 'ship'])->name('orders.ship');
    Route::post('orders/{order:order_number}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::resource('products', ProductController::class)->except(['show']);

    // Product images (clean multipart POST, separate from the text form).
    Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
    Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
    Route::put('products/{product}/images/{image}/primary', [ProductImageController::class, 'setPrimary'])->name('products.images.primary');

    Route::get('stock-import', [StockImportController::class, 'index'])->name('stock-import.index');
    Route::post('stock-import/preview', [StockImportController::class, 'preview'])->name('stock-import.preview');
    Route::post('stock-import/apply', [StockImportController::class, 'apply'])->name('stock-import.apply');
    Route::post('stock-import/{activityLog}/undo', [StockImportController::class, 'undo'])->name('stock-import.undo');
});
