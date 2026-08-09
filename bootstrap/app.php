<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAdminLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Symfony\Component\HttpFoundation\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust all proxies. This USED to be a Cloudflare CIDR allowlist, which
        // was correct while the plan was to sit behind Cloudflare — but the
        // production domain is a DIRECT Railway CNAME (www.retab.com.sa; the
        // zone lives on Bluvalt, not Cloudflare), so Railway's edge IP matches
        // nothing in that list and Laravel silently ignores every X-Forwarded-*
        // header: it sees plain http and reports the proxy's IP as the client.
        //
        // Sky Amman hit exactly this during its own domain switchover. The
        // stakes are higher here, because EVERY `throttle:` bucket in this app
        // keys on the client IP — with the header ignored, all visitors share
        // one identity and the rate limits collapse into a single global bucket
        // (see the throttle-prefix gotcha in CLAUDE.md for how that fails). It
        // also breaks the IPs recorded on product requests and audit logs.
        //
        // '*' is safe here specifically because the container is ONLY reachable
        // through Railway's edge; there is no public route to it that would let
        // an external client forge X-Forwarded-For. ⚠️ If Cloudflare is ever put
        // back in front, this can return to an allowlist (CF ranges +
        // Railway's hop), but do not narrow it while the CNAME is direct.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff' => EnsureUserIsStaff::class,
            'admin' => EnsureUserIsAdmin::class,
            'permission' => RequirePermission::class,
            'admin.locale' => SetAdminLocale::class,
        ]);

        // Language cookies are read as plaintext, so they must be excluded from
        // Laravel's cookie encryption or they get dropped on read. `admin_locale`
        // = the admin toggle (client-set); `locale` = the storefront's long-lived
        // guest preference (server-set in LocaleController). Neither is sensitive
        // and SetLocale re-validates the value against the ar|en whitelist.
        $middleware->encryptCookies(except: ['admin_locale', 'locale']);

        // Server-to-server webhooks (OTO, payment gateways) can't carry a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
