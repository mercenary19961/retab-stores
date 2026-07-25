<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * Persist the visitor's locale choice in the server session.
     * Hit by a fetch POST from LanguageContext on toggle (no Inertia visit).
     */
    public function set(Request $request, string $locale): JsonResponse
    {
        if (! in_array($locale, ['ar', 'en'], true)) {
            return response()->json(['ok' => false], 422);
        }

        $request->session()->put('locale', $locale);

        // Persist to the account so the preference follows a signed-in user
        // across devices/sessions.
        $request->user()?->update(['locale' => $locale]);

        // Long-lived cookie so a guest's choice survives an expired session /
        // closed browser. Server-readable (unlike localStorage) → correct
        // first-paint dir with no flash. Plaintext (excluded from encryption).
        Cookie::queue('locale', $locale, 60 * 24 * 365);

        return response()->json(['ok' => true, 'locale' => $locale]);
    }
}
