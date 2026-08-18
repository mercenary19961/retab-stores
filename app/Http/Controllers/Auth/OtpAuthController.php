<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\WhatsAppUnavailableException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\CartService;
use App\Services\TurnstileVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * WhatsApp OTP sign-in / sign-up. One flow: enter phone → receive a WhatsApp code
 * → verify. A first-time phone creates a minimal customer account (phone only;
 * they complete their profile later). Mirrors the multi-method identity model —
 * a user may have only a phone.
 */
class OtpAuthController extends Controller
{
    public function __construct(
        protected OtpService $otp,
        protected CartService $cart,
    ) {}

    public function create()
    {
        // The page decides whether to show the phone form or a "use email instead"
        // notice from the shared `whatsappAuth` prop, so there is exactly one source
        // of truth for whether this door opens.
        return Inertia::render('auth/whatsapp-login');
    }

    public function send(Request $request, TurnstileVerifier $turnstile)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        // Bot gate — every OTP send costs a real WhatsApp message. The verifier
        // no-ops while TURNSTILE_SECRET_KEY is unset (dev/staging).
        if (! $turnstile->verify($request->input('cf-turnstile-response'), $request->ip())) {
            return back()->withErrors(['phone' => __('messages.security.verify_failed')]);
        }

        try {
            $this->otp->request($data['phone']);
        } catch (WhatsAppUnavailableException $e) {
            // Form-level, NOT on `phone`. The channel is down; the number they typed
            // is fine, and marking it invalid would send them off correcting a
            // perfectly good phone number.
            return back()->withErrors(['whatsapp' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            // The resend cooldown, which genuinely is about this phone.
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return back(303);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'code' => ['required', 'string', 'max:6'],
        ]);

        if (! $this->otp->verify($data['phone'], $data['code'])) {
            return back()->withErrors(['code' => __('messages.otp.invalid')]);
        }

        $phone = $this->otp->normalize($data['phone']);

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            // forceCreate: `role` is a guarded privilege field (not mass-assignable).
            $user = User::forceCreate([
                'phone' => $phone,
                'role' => 'customer',
                'locale' => 'ar',
                'phone_verified_at' => now(),
            ]);
        } elseif (! $user->phone_verified_at) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $this->cart->mergeGuestInto($user);

        return redirect()->intended(route('account.dashboard'));
    }
}
