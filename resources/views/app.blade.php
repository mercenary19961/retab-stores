<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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
        @inertia
    </body>
</html>
