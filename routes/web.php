<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\Webhooks\MoyasarWebhookController;
use App\Http\Controllers\Webhooks\OtoWebhookController;
use App\Http\Controllers\Webhooks\TamaraWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Locale toggle — fetch POST from LanguageContext, persists to session (no Inertia visit).
Route::post('/locale/{locale}', [LocaleController::class, 'set'])
    ->whereIn('locale', ['ar', 'en'])
    ->middleware('throttle:30,1')
    ->name('locale.set');

// Crawler endpoints (routes, not static files — absolute URLs per environment).
Route::get('/sitemap.xml', [\App\Http\Controllers\SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [\App\Http\Controllers\SeoController::class, 'robots'])->name('seo.robots');

// Storefront (AR-first).
Route::get('/', [ShopController::class, 'index'])->name('home');
Route::get('/shop', [ShopController::class, 'catalogue'])->name('shop.catalogue');
Route::get('/pages/{slug}', [\App\Http\Controllers\PageController::class, 'show'])->name('pages.show');
Route::get('/products/{product:slug}', [ShopController::class, 'show'])->name('shop.product');

// Cart (public POSTs — rate-limited against scripted abuse).
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart', [CartController::class, 'add'])->middleware('throttle:60,1')->name('cart.add');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->middleware('throttle:60,1')->name('cart.update');
Route::delete('/cart/items/{item}', [CartController::class, 'remove'])->middleware('throttle:60,1')->name('cart.remove');

// Checkout.
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
Route::get('/orders/{order:order_number}', [CheckoutController::class, 'confirmation'])->name('orders.show');

// Server-to-server webhooks (CSRF-exempt via the webhooks/* rule).
Route::post('/webhooks/oto', [OtoWebhookController::class, 'handle'])->name('webhooks.oto');
Route::post('/webhooks/moyasar', [MoyasarWebhookController::class, 'handle'])->name('webhooks.moyasar');
Route::post('/webhooks/tamara', [TamaraWebhookController::class, 'handle'])->name('webhooks.tamara');
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])->name('webhooks.whatsapp');

Route::middleware(['auth'])->group(function () {
    // Legacy starter-kit path: staff → back-office, customers → their account.
    Route::get('dashboard', function () {
        return redirect(\Illuminate\Support\Facades\Auth::user()->isStaff() ? route('admin.dashboard') : route('account.dashboard'));
    })->name('dashboard');

    // Customer account (storefront, AR-first).
    Route::get('account', [AccountController::class, 'dashboard'])->name('account.dashboard');
    Route::get('account/profile', [AccountController::class, 'editProfile'])->name('account.profile.edit');
    Route::patch('account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');

    // Reviews (verified-purchase) + helpful votes.
    Route::post('products/{product:slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:10,1')->name('reviews.store');
    Route::post('reviews/{review}/helpful', [ReviewController::class, 'helpful'])->middleware('throttle:30,1')->name('reviews.helpful');

    // Wishlist.
    Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('wishlist/{product:slug}/toggle', [WishlistController::class, 'toggle'])->middleware('throttle:30,1')->name('wishlist.toggle');

    // Returns (defect/damage only, within 3 days of delivery, with photos).
    Route::get('orders/{order:order_number}/return', [ReturnController::class, 'create'])->name('returns.create');
    Route::post('orders/{order:order_number}/return', [ReturnController::class, 'store'])->middleware('throttle:5,1')->name('returns.store');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
