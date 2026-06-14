<!DOCTYPE html>
@php
    use App\Domains\Tenancy\Support\MarketingSeoContext;

    $isMarketingPage = MarketingSeoContext::isMarketingRequest(request());
    $appearance = 'light';
    $centralSeo = null;

    if (! $isMarketingPage && auth()->check()) {
        $appearance = app(\App\Domains\Auth\Services\UserPreferenceService::class)->appearance(auth()->user());
    }

    if ($isMarketingPage) {
        $centralSettings = app(\App\Domains\Tenancy\Services\CentralSettingsService::class);
        $socialUrls = collect($centralSettings->socialLinks())
            ->pluck('url')
            ->filter(fn ($url) => is_string($url) && $url !== '')
            ->values()
            ->all();

        $centralSeo = app(\App\Domains\Tenancy\Services\CentralSeoService::class)->meta(
            MarketingSeoContext::pageKey(request()),
            config('app.name', 'helpefi'),
            $centralSettings->trialDays(),
            $centralSettings->currency(),
            $socialUrls,
        );
    }
@endphp
<html lang="en" dir="ltr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0066CC">
        <link rel="icon" href="/favicon.png" type="image/png" sizes="32x32">
        <link rel="icon" href="/favicon-16.png" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @if ($isMarketingPage)
            <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
            <link rel="dns-prefetch" href="https://www.google-analytics.com">
        @endif
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
