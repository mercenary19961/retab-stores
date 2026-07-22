<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\User;
use App\Notifications\ProductRequestedNotification;
use App\Services\TurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * Records a customer's "I want this" tap on a Coming-Soon product — a demand
 * signal the store follows up on (WhatsApp). Signed-in customers are one click
 * (their account carries the contact); guests supply a phone and pass the
 * Turnstile bot gate. Never sells anything: buyability stays is_active-gated.
 */
class ProductRequestController extends Controller
{
    public function store(Request $request, Product $product, TurnstileVerifier $turnstile): RedirectResponse
    {
        // Only Coming-Soon products accept requests; a live or plainly-hidden
        // product 404s so the button can't be replayed against arbitrary products.
        abort_unless($product->isComingSoon(), 404);

        $user = $request->user();

        if ($user) {
            $phone = $user->phone; // may be null (e.g. Google-only account) — user_id still identifies them
        } else {
            $data = $request->validate([
                'phone' => ['required', 'string', 'max:20'],
            ]);
            // Bot gate for guests (no account to trace); no-ops without a secret key.
            if (! $turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
                return back()->withErrors(['phone' => __('messages.security.verify_failed')]);
            }
            $phone = $data['phone'];
        }

        // Collapse repeat taps: one open (unhandled) request per customer per product
        // keeps the signal honest and avoids spamming staff on double-clicks.
        $already = ProductRequest::where('product_id', $product->id)
            ->whereNull('handled_at')
            ->where(fn ($q) => $user ? $q->where('user_id', $user->id) : $q->where('phone', $phone))
            ->exists();

        if (! $already) {
            $productRequest = ProductRequest::create([
                'product_id' => $product->id,
                'user_id' => $user?->id,
                'phone' => $phone,
                'ip' => $request->ip(),
            ]);

            Notification::send(User::staff()->get(), new ProductRequestedNotification($productRequest));
        }

        return back(303)->with('success', __('messages.requests.received'));
    }
}
