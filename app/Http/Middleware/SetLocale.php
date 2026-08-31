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
        $sessionLocale = $request->session()->get('locale');
        $locale = self::resolve($request);

        app()->setLocale($locale);

        // Session had no locale but we resolved one from the account or the guest
        // cookie: seed the session so the shared Inertia `locale` prop + first-paint
        // <html dir> match what the server rendered.
        if ($sessionLocale === null && $locale !== 'ar') {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    /**
     * Work out the visitor's locale from the same sources handle() uses:
     * this session's live choice → a signed-in user's saved preference (follows
     * them across devices) → a returning guest's long-lived cookie → the AR default.
     *
     * 🔑 Public + static because the EXCEPTION HANDLER needs it too. This
     * middleware sits in the `web` group, but a 404 is thrown by the router before
     * any group middleware runs, and a 429 by ThrottleRequests, which Laravel's
     * middleware priority sorts ahead of unlisted custom middleware. In both cases
     * the branded error page would otherwise render in the AR default no matter
     * what the visitor chose. See bootstrap/app.php.
     *
     * ⚠️ Session-SAFE on purpose: on the 404 path StartSession has not run, so
     * the request genuinely has no session store and asking for one throws. The
     * long-lived `locale` cookie is what still answers correctly there.
     */
    public static function resolve(Request $request): string
    {
        $locale = ($request->hasSession() ? $request->session()->get('locale') : null)
            ?? ($request->hasSession() ? $request->user()?->locale : null)
            ?? $request->cookie('locale')
            ?? 'ar';

        return in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
    }
}
