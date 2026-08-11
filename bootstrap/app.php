<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsStaff;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAdminLocale;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustProxiedClientIp;
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
        // Resolve the real visitor IP before TrustProxies reads the headers.
        // On this stack (Cloudflare → Railway edge → container) the visitor's
        // address is NOT in X-Forwarded-For — Railway overwrites that header
        // with its own hop chain — so trusting X-Forwarded-For alone made every
        // visitor resolve to a Railway edge IP, collapsing every `throttle:`
        // bucket. Full measurements + why X-Real-IP is the trustworthy source
        // are in the middleware's docblock. Must stay ahead of TrustProxies.
        $middleware->prepend(TrustProxiedClientIp::class);

        // Trust the calling proxy. '*' does NOT mean "trust anything": Laravel
        // maps it to the immediate caller only (REMOTE_ADDR — always Railway's
        // private 100.64.x.x hop here), which is exactly the one hop in front of
        // us. Narrowing it to a CIDR allowlist is pointless on Railway, whose
        // edge IPs are neither published nor stable, and would silently drop
        // X-Forwarded-Proto — leaving Laravel to think a real HTTPS request was
        // plain http. The client IP is handled above, not by widening this.
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
