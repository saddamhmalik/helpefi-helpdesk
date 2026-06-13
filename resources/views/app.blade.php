<!DOCTYPE html>
@php
    $isMarketingPage = request()->routeIs('central.home', 'central.login', 'central.register');
    $appearance = 'light';
    $centralSeo = null;

    if (! $isMarketingPage && auth()->check()) {
        $appearance = app(\App\Domains\Auth\Services\UserPreferenceService::class)->appearance(auth()->user());
    }

    if ($isMarketingPage) {
        $centralPage = match (true) {
            request()->routeIs('central.register') => 'register',
            request()->routeIs('central.login') => 'login',
            default => 'home',
        };

        $centralSettings = app(\App\Domains\Tenancy\Services\CentralSettingsService::class);

        $centralSeo = app(\App\Domains\Tenancy\Services\CentralSeoService::class)->meta(
            $centralPage,
            config('app.name', 'helpefi'),
            $centralSettings->trialDays(),
            $centralSettings->currency(),
        );
    }
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar']) ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0066CC">
        <link rel="icon" href="/favicon.png" type="image/png" sizes="32x32">
        <link rel="icon" href="/favicon-16.png" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <title inertia>{{ $centralSeo['title'] ?? config('app.name', 'helpefi') }}</title>
        @if ($centralSeo)
            @include('partials.central-seo', ['seo' => $centralSeo])
        @endif
        <script>
            (function () {
                var isMarketing = @json($isMarketingPage);

                if (isMarketing) {
                    return;
                }

                var pref = @json($appearance);

                if (!@json(auth()->check())) {
                    try {
                        var guest = localStorage.getItem('appearance:guest');

                        if (guest === 'light' || guest === 'dark' || guest === 'system') {
                            pref = guest;
                        }
                    } catch (e) {}
                }

                var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                if (dark) {
                    document.documentElement.classList.add('dark');
                    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', '#0f172a');
                }
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
        @inertia
    </body>
</html>
