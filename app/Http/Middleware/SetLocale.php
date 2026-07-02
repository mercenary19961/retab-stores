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
        $locale = session('locale', 'ar');

        if (in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
