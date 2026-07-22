<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Google Consent Mode v2. Everything starts DENIED before any tag can
             read consent state, so nothing tracks without a choice. The cookie
             banner sends the 'update' on choice; repeat visitors are re-granted
             here from the cookie so their tags work on first paint. Kept off
             /admin/* (staff aren't site traffic) and INLINE on purpose: it must
             run before GTM, which a deferred Vite bundle can't guarantee. --}}
        @unless (request()->is('admin', 'admin/*'))
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('consent', 'default', {
                    ad_storage: 'denied',
                    ad_user_data: 'denied',
                    ad_personalization: 'denied',
                    analytics_storage: 'denied',
                    functionality_storage: 'granted',
                    personalization_storage: 'denied',
                    security_storage: 'granted',
                    wait_for_update: 500
                });
                try {
                    var m = document.cookie.match(/(?:^|;\s*)retab_consent=([^;]*)/);
                    if (m) {
                        var v = JSON.parse(decodeURIComponent(m[1]));
                        gtag('consent', 'update', {
                            ad_storage: v.marketing ? 'granted' : 'denied',
                            ad_user_data: v.marketing ? 'granted' : 'denied',
                            ad_personalization: v.marketing ? 'granted' : 'denied',
                            analytics_storage: v.analytics ? 'granted' : 'denied',
                            personalization_storage: v.marketing ? 'granted' : 'denied'
                        });
                    }
                } catch (e) { /* malformed cookie, stay denied */ }
            </script>

            {{-- GTM loads only once a container id is set (GTM_CONTAINER_ID). GA4
                 and the ad tags are configured inside the container, not here. --}}
            @if ($gtmId = config('services.gtm.container_id'))
                <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','{{ $gtmId }}');</script>
            @endif
        @endunless

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        {{-- Preload the two most-used brand-font weights (body + bold) so they
             arrive before first paint. Fonts fetch in CORS mode, so `crossorigin`
             is required here or the browser downloads each file twice. --}}
        <link rel="preload" as="font" type="font/woff2" href="{{ Vite::asset('resources/fonts/thmanyahsans-Regular.woff2') }}" crossorigin>
        <link rel="preload" as="font" type="font/woff2" href="{{ Vite::asset('resources/fonts/thmanyahsans-Bold.woff2') }}" crossorigin>

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        {{-- GTM no-JS fallback (first element in body), only when a container is set. --}}
        @unless (request()->is('admin', 'admin/*'))
            @if ($gtmId = config('services.gtm.container_id'))
                <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
                    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
            @endif
        @endunless

        @inertia
    </body>
</html>
