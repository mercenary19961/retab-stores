<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the session locale to the app before the response renders.
     * AR-first: default to Arabic when nothing is stored (Saudi market).
     * The session cookie is the single source of truth — no localStorage.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Resolution order: this session's live choice → a signed-in user's saved
        // preference (follows them across devices) → a returning guest's long-lived
        // cookie → the AR default.
        $sessionLocale = $request->session()->get('locale');
        $locale = $sessionLocale
            ?? $request->user()?->locale
            ?? $request->cookie('locale')
            ?? 'ar';

        if (! in_array($locale, ['ar', 'en'], true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);

        // Session had no locale but we resolved one from the account or the guest
        // cookie: seed the session so the shared Inertia `locale` prop + first-paint
        // <html dir> match what the server rendered.
        if ($sessionLocale === null && $locale !== 'ar') {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
