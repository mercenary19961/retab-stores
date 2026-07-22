<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Cloudflare CIDRs + RFC 1918 (Railway's internal hop). Locking
        // trustProxies (never '*') prevents X-Forwarded-For spoofing, which
        // would let clients forge IPs for rate limits and audit logs. Update
        // from https://www.cloudflare.com/ips/ when CF publishes new ranges.
        $middleware->trustProxies(at: [
            '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16',
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22',
            '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
            '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22',
            '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32',
            '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29',
            '2c0f:f248::/32',
        ], headers: \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR
            | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST
            | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT
            | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO);

        $middleware->web(append: [
            SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'staff' => \App\Http\Middleware\EnsureUserIsStaff::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'permission' => \App\Http\Middleware\RequirePermission::class,
            'admin.locale' => \App\Http\Middleware\SetAdminLocale::class,
        ]);

        // Set client-side (plaintext) by the admin language toggle, so it must be
        // excluded from Laravel's cookie encryption or it gets dropped on read.
        $middleware->encryptCookies(except: ['admin_locale']);

        // Server-to-server webhooks (OTO, payment gateways) can't carry a CSRF token.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
