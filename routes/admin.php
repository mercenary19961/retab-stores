<?php

use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReturnController;
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

    // WhatsApp marketing — template registry + campaign sender.
    Route::get('marketing', [\App\Http\Controllers\Admin\MarketingController::class, 'index'])->name('marketing.index');
    Route::post('marketing/templates', [\App\Http\Controllers\Admin\MarketingController::class, 'storeTemplate'])->name('marketing.templates.store');
    Route::put('marketing/templates/{template}', [\App\Http\Controllers\Admin\MarketingController::class, 'updateTemplate'])->name('marketing.templates.update');
    Route::post('marketing/campaigns', [\App\Http\Controllers\Admin\MarketingController::class, 'storeCampaign'])->name('marketing.campaigns.store');

    // Customer directory (read-only).
    Route::get('customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'show'])->name('customers.show');

    // Store settings + CMS pages.
    Route::get('settings', [\App\Http\Controllers\Admin\SettingController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');
    Route::resource('content-pages', \App\Http\Controllers\Admin\ContentPageController::class)
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->parameters(['content-pages' => 'contentPage']);

    // Returns review + resolution.
    Route::get('returns', [ReturnController::class, 'index'])->name('returns.index');
    Route::get('returns/{orderReturn}', [ReturnController::class, 'show'])->name('returns.show');
    Route::post('returns/{orderReturn}/approve', [ReturnController::class, 'approve'])->name('returns.approve');
    Route::post('returns/{orderReturn}/reject', [ReturnController::class, 'reject'])->name('returns.reject');
    Route::post('returns/{orderReturn}/exchange', [ReturnController::class, 'exchange'])->name('returns.exchange');
    Route::post('returns/{orderReturn}/refund', [ReturnController::class, 'refund'])->name('returns.refund');

    Route::get('stock-import', [StockImportController::class, 'index'])->name('stock-import.index');
    Route::post('stock-import/preview', [StockImportController::class, 'preview'])->name('stock-import.preview');
    Route::post('stock-import/apply', [StockImportController::class, 'apply'])->name('stock-import.apply');
    Route::post('stock-import/{activityLog}/undo', [StockImportController::class, 'undo'])->name('stock-import.undo');

    // Change log — audit history + per-entry revert.
    Route::get('change-log', [\App\Http\Controllers\Admin\ChangeLogController::class, 'index'])->name('change-log.index');
    Route::post('change-log/{activityLog}/revert', [\App\Http\Controllers\Admin\ChangeLogController::class, 'revert'])->name('change-log.revert');
});
