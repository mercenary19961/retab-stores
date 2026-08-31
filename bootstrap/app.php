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
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
        // Turn raw framework error responses into the store's own UI.
        //
        // 🔑 Never in `testing`: the suite asserts the real status codes
        // (assertForbidden(), assertNotFound(), …), not a rendered page.
        //
        // In `local` only SERVER errors keep the framework's page, because that
        // stack trace is the whole point in dev. A 403/404/419/429 carries no
        // trace worth reading, so those render branded in dev too — otherwise the
        // storefront's most common error states could only ever be seen in prod.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (app()->environment('testing')) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status >= 500 && app()->environment('local')) {
                return $response;
            }

            // 🔴 Re-resolve the visitor's language before rendering ANYTHING below.
            // SetLocale lives in the `web` group, but a 404 is thrown by the router
            // before group middleware runs and a 429 by ThrottleRequests, which
            // middleware priority sorts ahead of it — so at this point the locale is
            // still the AR default and, worse, Inertia has no shared props at all
            // (HandleInertiaRequests never ran), which is why `locale` has to be
            // passed explicitly below rather than relying on the shared prop.
            // Measured in a browser: an English visitor got an Arabic error page.
            $locale = SetLocale::resolve($request);
            app()->setLocale($locale);

            // A refused or too-fast INLINE action: a stale write button that
            // client-side gating should have hidden (a race, or a direct poke),
            // or a form submitted faster than its rate limit allows. Keep the
            // user on their page with a flash the admin toast layer renders,
            // never a jarring full-page swap to the error screen.
            //
            // ⚠️ Scoped to the ADMIN deliberately. The storefront has no global
            // flash renderer (only admin-layout mounts <AdminToasts/>), so bouncing
            // a shopper back would make the button silently do nothing — worse than
            // an error page. They get the branded page below instead, which at
            // least says what happened and, for a 429, when they can retry.
            // `password.update` is named explicitly because the admin submits it
            // from a modal and its path is `settings/password`, not `admin/*`.
            $inAdmin = $request->is('admin/*') || $request->routeIs('password.update');

            if ($inAdmin && $request->header('X-Inertia') && ! $request->isMethod('GET')) {
                if ($status === 403) {
                    return back()->with('error', __('messages.admin.no_permission'));
                }

                if ($status === 429) {
                    return back()->with('error', __('messages.errors.too_many_requests'));
                }
            }

            // Expired CSRF token: the conventional recovery is to bounce back so
            // the freshly-issued token is picked up, rather than show an error.
            if ($status === 419) {
                return back()->with('error', __('messages.errors.page_expired'));
            }

            // Everything else with a friendly page → the branded full-page error.
            // JSON/API clients keep the machine-readable response.
            if (! $request->expectsJson() && in_array($status, [403, 404, 429, 500, 503], true)) {
                // `locale` is passed explicitly because app.tsx seeds the i18n
                // instance from it, and the shared prop does not exist here.
                $props = ['status' => $status, 'locale' => $locale];

                // Seconds until the limit clears, so the page can count down
                // rather than vaguely say "later". Laravel's ThrottleRequests puts
                // it on Retry-After; when it is absent the page simply omits the
                // timer instead of inventing a number.
                if ($status === 429 && ($retryAfter = (int) $response->headers->get('Retry-After')) > 0) {
                    $props['retryAfter'] = $retryAfter;
                }

                $branded = Inertia::render('errors/error', $props)
                    ->toResponse($request)
                    ->setStatusCode($status);

                // Inertia builds a FRESH response, so the rate-limit headers a
                // client (or a crawler backing off) may act on are lost unless
                // they are carried across by hand.
                foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'] as $header) {
                    if ($response->headers->has($header)) {
                        $branded->headers->set($header, $response->headers->get($header));
                    }
                }

                return $branded;
            }

            return $response;
        });
    })->create();
