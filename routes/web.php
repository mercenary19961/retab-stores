<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductRequestController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\Webhooks\MoyasarWebhookController;
use App\Http\Controllers\Webhooks\OtoWebhookController;
use App\Http\Controllers\Webhooks\TamaraWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * ⚠️ Every `throttle:` below carries a PREFIX (the 3rd argument), and it is not
 * decoration — it is required for correctness.
 *
 * Laravel's unnamed throttle keys its counter on `sha1(domain|IP)` via
 * ThrottleRequests::resolveRequestSignature() — the route URI is NOT part of the
 * key. So without a prefix, every rate-limited route shares ONE bucket per
 * visitor while each compares that shared count against its own limit, and the
 * strictest route starts rejecting once the visitor's COMBINED requests pass it.
 *
 * That was a live revenue bug: a shopper who added ~11 items to their cart
 * (allowed, limit 60) then clicked checkout (limit 10) got a 429 and could not
 * pay. Found 2026-08-06 when new cart tests pushed the shared counter over.
 * The prefix gives each group its own counter, which is what the numbers here
 * were always meant to express.
 */

// Locale toggle — fetch POST from LanguageContext, persists to session (no Inertia visit).
Route::post('/locale/{locale}', [LocaleController::class, 'set'])
    ->whereIn('locale', ['ar', 'en'])
    ->middleware('throttle:30,1,locale')
    ->name('locale.set');

// Crawler endpoints (routes, not static files — absolute URLs per environment).
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');

// Storefront (AR-first).
Route::get('/', [ShopController::class, 'index'])->name('home');
Route::get('/shop', [ShopController::class, 'catalogue'])->name('shop.catalogue');
// Catalogue search index (JSON): the whole small, cached catalogue, fetched once
// so the typeahead filters in-memory (zero DB hits / round-trips per keystroke).
Route::get('/shop/search-index', [ShopController::class, 'searchIndex'])->middleware('throttle:60,1,search')->name('shop.search-index');
// Physical shops (map + directions). Registered before the CMS catch-all so the
// footer's /pages/branches link resolves here, not to a content page.
Route::get('/pages/branches', [BranchController::class, 'index'])->name('branches');
// Designed About page — same story as branches: declared before the catch-all so
// this slug renders the custom layout instead of the `about` content_pages row.
Route::get('/pages/about', [AboutController::class, 'index'])->name('about');
Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');
// On a slug miss, consult the 301 redirect map (old Zid URLs) before 404-ing.
Route::get('/products/{product:slug}', [ShopController::class, 'show'])
    ->name('shop.product')
    ->missing(fn ($request) => (new RedirectController)->missingProduct($request));
// "I want this" demand signal for Coming-Soon products (guests allowed → guests
// supply a phone + pass the Turnstile bot gate; signed-in users are one click).
Route::post('/products/{product:slug}/request', [ProductRequestController::class, 'store'])
    ->middleware('throttle:5,1,product-request')
    ->name('shop.product.request');

// Cart (public POSTs — rate-limited against scripted abuse).
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart', [CartController::class, 'add'])->middleware('throttle:60,1,cart')->name('cart.add');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->middleware('throttle:60,1,cart')->name('cart.update');
Route::delete('/cart/items/{item}', [CartController::class, 'remove'])->middleware('throttle:60,1,cart')->name('cart.remove');
// Coupon apply is a code-guessing surface, so it gets a tighter limit than the
// cart mutations above — it only previews (nothing is redeemed until checkout).
Route::post('/cart/coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:10,1,coupon')->name('cart.coupon.apply');
Route::delete('/cart/coupon', [CartController::class, 'removeCoupon'])->middleware('throttle:30,1,coupon')->name('cart.coupon.remove');

// Checkout.
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1,checkout')->name('checkout.store');
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
        return redirect(Auth::user()->isStaff() ? route('admin.dashboard') : route('account.dashboard'));
    })->name('dashboard');

    // Customer account (storefront, AR-first).
    Route::get('account', [AccountController::class, 'dashboard'])->name('account.dashboard');
    Route::get('account/profile', [AccountController::class, 'editProfile'])->name('account.profile.edit');
    Route::patch('account/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');

    // Reviews (verified-purchase) + helpful votes.
    Route::post('products/{product:slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:10,1,reviews')->name('reviews.store');
    Route::post('reviews/{review}/helpful', [ReviewController::class, 'helpful'])->middleware('throttle:30,1,reviews-vote')->name('reviews.helpful');

    // Wishlist.
    Route::get('wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('wishlist/{product:slug}/toggle', [WishlistController::class, 'toggle'])->middleware('throttle:30,1,wishlist')->name('wishlist.toggle');

    // Returns (defect/damage only, within 3 days of delivery, with photos).
    Route::get('orders/{order:order_number}/return', [ReturnController::class, 'create'])->name('returns.create');
    Route::post('orders/{order:order_number}/return', [ReturnController::class, 'store'])->middleware('throttle:5,1,returns')->name('returns.store');
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
