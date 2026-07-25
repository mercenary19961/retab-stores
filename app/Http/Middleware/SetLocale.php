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
        // Session is the live source of truth; fall back to a signed-in user's
        // saved preference (follows them across devices), then the AR default.
        $sessionLocale = $request->session()->get('locale');
        $locale = $sessionLocale ?? $request->user()?->locale ?? 'ar';

        if (! in_array($locale, ['ar', 'en'], true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);

        // Fresh login on a new device (session has no locale yet): seed it from
        // the user's preference so the shared Inertia `locale` prop + first-paint
        // <html dir> match what the server rendered.
        if ($sessionLocale === null && $request->user() !== null) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
