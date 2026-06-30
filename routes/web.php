<?php

use App\Http\Controllers\CartController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\Webhooks\MoyasarWebhookController;
use App\Http\Controllers\Webhooks\OtoWebhookController;
use App\Http\Controllers\Webhooks\TamaraWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Storefront (AR-first).
Route::get('/', [ShopController::class, 'index'])->name('home');
Route::get('/products/{product:slug}', [ShopController::class, 'show'])->name('shop.product');

// Cart.
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{item}', [CartController::class, 'remove'])->name('cart.remove');

// Server-to-server webhooks (CSRF-exempt via the webhooks/* rule).
Route::post('/webhooks/oto', [OtoWebhookController::class, 'handle'])->name('webhooks.oto');
Route::post('/webhooks/moyasar', [MoyasarWebhookController::class, 'handle'])->name('webhooks.moyasar');
Route::post('/webhooks/tamara', [TamaraWebhookController::class, 'handle'])->name('webhooks.tamara');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
