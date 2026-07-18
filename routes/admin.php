<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReturnController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\StockImportController;
use Illuminate\Support\Facades\Route;

// Back-office (EN-first). Staff only — admin or editor.
Route::middleware(['auth', 'staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('search', [\App\Http\Controllers\Admin\GlobalSearchController::class, 'search'])->name('search');
    Route::put('preferences/table-widths', [\App\Http\Controllers\Admin\PreferenceController::class, 'tableWidths'])->name('preferences.table-widths');

    // Orders.
    Route::get('orders', [OrderController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('orders/export', [OrderController::class, 'export'])->middleware('permission:orders.export')->name('orders.export');
    Route::get('orders/{order:order_number}', [OrderController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::middleware('permission:orders.manage')->group(function () {
        Route::post('orders/{order:order_number}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order:order_number}/unavailable', [OrderController::class, 'markUnavailable'])->name('orders.unavailable');
        Route::post('orders/{order:order_number}/ship', [OrderController::class, 'ship'])->name('orders.ship');
        Route::post('orders/{order:order_number}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    });

    // Products.
    Route::get('products/export', [ProductController::class, 'export'])->middleware('permission:products.view')->name('products.export');
    Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::get('products/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.edit')->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit')->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');

    // Product images (clean multipart POST, separate from the text form).
    Route::middleware('permission:products.edit')->group(function () {
        Route::post('products/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');
        Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('products.images.destroy');
        Route::put('products/{product}/images/{image}/primary', [ProductImageController::class, 'setPrimary'])->name('products.images.primary');
    });

    // WhatsApp marketing — template registry + campaign sender.
    Route::get('marketing', [\App\Http\Controllers\Admin\MarketingController::class, 'index'])->middleware('permission:marketing.view')->name('marketing.index');
    Route::middleware('permission:marketing.send')->group(function () {
        Route::post('marketing/templates', [\App\Http\Controllers\Admin\MarketingController::class, 'storeTemplate'])->name('marketing.templates.store');
        Route::put('marketing/templates/{template}', [\App\Http\Controllers\Admin\MarketingController::class, 'updateTemplate'])->name('marketing.templates.update');
        Route::post('marketing/campaigns', [\App\Http\Controllers\Admin\MarketingController::class, 'storeCampaign'])->name('marketing.campaigns.store');
    });

    // Customer directory (read-only).
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('customers', [\App\Http\Controllers\Admin\CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/export', [\App\Http\Controllers\Admin\CustomerController::class, 'export'])->name('customers.export');
        Route::get('customers/{customer}/detail', [\App\Http\Controllers\Admin\CustomerController::class, 'detail'])->name('customers.detail');
        Route::get('customers/{customer}', [\App\Http\Controllers\Admin\CustomerController::class, 'show'])->name('customers.show');
    });

    // Store settings + CMS pages.
    Route::get('settings', [\App\Http\Controllers\Admin\SettingController::class, 'edit'])->middleware('permission:settings.view')->name('settings.edit');
    Route::put('settings', [\App\Http\Controllers\Admin\SettingController::class, 'update'])->middleware('permission:settings.edit')->name('settings.update');
    // Admin-only safeguard: restore editable content to the project-handover defaults.
    Route::post('settings/reset', [\App\Http\Controllers\Admin\SettingController::class, 'reset'])->name('settings.reset');
    // Content pages are edit-only — the three baseline pages ship seeded; no adding/removing.
    Route::resource('content-pages', \App\Http\Controllers\Admin\ContentPageController::class)
        ->only(['index', 'edit', 'update'])
        ->middleware('permission:content_pages.view')
        ->parameters(['content-pages' => 'contentPage']);

    // Staff & access control — admin only.
    Route::middleware('admin')->group(function () {
        Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
        Route::post('users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}/permissions', [\App\Http\Controllers\Admin\UserController::class, 'updatePermissions'])->name('users.permissions');
        Route::delete('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');
    });

    // Curated client reviews (Google Maps testimonials pool) + bulk import.
    Route::get('client-reviews/import', [\App\Http\Controllers\Admin\ClientReviewController::class, 'importForm'])->middleware('permission:reviews.manage')->name('client-reviews.import');
    Route::post('client-reviews/import', [\App\Http\Controllers\Admin\ClientReviewController::class, 'importStore'])->middleware('permission:reviews.manage')->name('client-reviews.import.store');
    Route::get('client-reviews', [\App\Http\Controllers\Admin\ClientReviewController::class, 'index'])->middleware('permission:reviews.view')->name('client-reviews.index');
    Route::resource('client-reviews', \App\Http\Controllers\Admin\ClientReviewController::class)
        ->only(['create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('permission:reviews.manage')
        ->parameters(['client-reviews' => 'clientReview']);

    // Returns review + resolution.
    Route::get('returns', [ReturnController::class, 'index'])->middleware('permission:returns.view')->name('returns.index');
    Route::get('returns/export', [ReturnController::class, 'export'])->middleware('permission:returns.view')->name('returns.export');
    Route::get('returns/{orderReturn}', [ReturnController::class, 'show'])->middleware('permission:returns.view')->name('returns.show');
    Route::middleware('permission:returns.resolve')->group(function () {
        Route::post('returns/{orderReturn}/approve', [ReturnController::class, 'approve'])->name('returns.approve');
        Route::post('returns/{orderReturn}/reject', [ReturnController::class, 'reject'])->name('returns.reject');
        Route::post('returns/{orderReturn}/exchange', [ReturnController::class, 'exchange'])->name('returns.exchange');
        Route::post('returns/{orderReturn}/refund', [ReturnController::class, 'refund'])->name('returns.refund');
    });

    // Inventory (SMACC stock import).
    Route::get('stock-import', [StockImportController::class, 'index'])->middleware('permission:inventory.view')->name('stock-import.index');
    Route::get('stock-import/export', [StockImportController::class, 'export'])->middleware('permission:inventory.view')->name('stock-import.export');
    Route::middleware('permission:inventory.import')->group(function () {
        Route::post('stock-import/preview', [StockImportController::class, 'preview'])->name('stock-import.preview');
        Route::post('stock-import/apply', [StockImportController::class, 'apply'])->name('stock-import.apply');
        Route::post('stock-import/{activityLog}/undo', [StockImportController::class, 'undo'])->name('stock-import.undo');
    });

    // Change log — audit history + per-entry revert.
    Route::get('change-log', [\App\Http\Controllers\Admin\ChangeLogController::class, 'index'])->middleware('permission:change_log.view')->name('change-log.index');
    Route::post('change-log/{activityLog}/revert', [\App\Http\Controllers\Admin\ChangeLogController::class, 'revert'])->middleware('permission:change_log.revert')->name('change-log.revert');
    Route::delete('change-log/undo/{section}', [\App\Http\Controllers\Admin\ChangeLogController::class, 'dismissUndo'])->middleware('permission:change_log.view')->name('change-log.dismiss-undo');
});
