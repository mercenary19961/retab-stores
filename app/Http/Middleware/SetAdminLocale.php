<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin panel has its OWN language (a client-side toggle, English-default),
 * independent of the storefront's session locale. The admin persists that choice
 * to an `admin_locale` cookie; this middleware applies it so server-rendered
 * admin strings (flash + validation messages) come out in the admin's chosen
 * language instead of the storefront session locale (which defaults to Arabic).
 *
 * Runs on the admin route group AFTER the global SetLocale, so it overrides.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('admin_locale', 'en');

        if (in_array($locale, ['ar', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
