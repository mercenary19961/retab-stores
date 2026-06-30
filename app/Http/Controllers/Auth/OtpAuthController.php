<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OtpService;
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
    ) {}

    public function create()
    {
        return Inertia::render('auth/whatsapp-login');
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ]);

        try {
            $this->otp->request($data['phone']);
        } catch (\RuntimeException $e) {
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
            return back()->withErrors(['code' => 'الرمز غير صحيح أو منتهي الصلاحية.']);
        }

        $phone = $this->otp->normalize($data['phone']);

        $user = User::where('phone', $phone)->first();
        if (! $user) {
            $user = User::create([
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

        return redirect()->intended(route('account.dashboard'));
    }
}
