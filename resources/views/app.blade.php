<!DOCTYPE html>
@php
    use App\Domains\Tenancy\Support\MarketingSeoContext;
    use App\Domains\Tenancy\Services\MarketingFirstPaintService;
    use Illuminate\Support\Facades\Cache;

    $isMarketingPage = MarketingSeoContext::isMarketingRequest(request());
    $marketingFirstPaint = $isMarketingPage
        ? app(MarketingFirstPaintService::class)->shellFor(request())
        : null;
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
            <link rel="preconnect" href="https://analytics.ahrefs.com" crossorigin>
            <link rel="dns-prefetch" href="https://analytics.ahrefs.com">
            <link rel="preload" as="image" href="/logo.png" fetchpriority="high">
        @endif
        <title inertia>{{ $centralSeo['title'] ?? config('app.name', 'helpefi') }}</title>
        @if ($isMarketingPage)
            @include('partials.central-seo-analytics')
            @if (is_array($centralSeo))
                @if (!empty($centralSeo['description']))
                    <meta name="description" content="{{ $centralSeo['description'] }}">
                @endif
                @if (!empty($centralSeo['robots']))
                    <meta name="robots" content="{{ $centralSeo['robots'] }}">
                @endif
                @if (!empty($centralSeo['canonical']))
                    <link rel="canonical" href="{{ $centralSeo['canonical'] }}">
                @endif

                @if (request()->routeIs('central.blog.index', 'central.blog.show'))
                    <link rel="alternate" type="application/rss+xml" title="{{ config('app.name', 'Helpefi').' Blog' }}" href="{{ rtrim((string) config('app.url'), '/').'/blog/rss.xml' }}">
                @endif

                <meta property="og:type" content="website">
                @if (!empty($centralSeo['canonical']))
                    <meta property="og:url" content="{{ $centralSeo['canonical'] }}">
                @endif
                @if (!empty($centralSeo['title']))
                    <meta property="og:title" content="{{ $centralSeo['title'] }}">
                @endif
                @if (!empty($centralSeo['ogDescription']))
                    <meta property="og:description" content="{{ $centralSeo['ogDescription'] }}">
                @elseif (!empty($centralSeo['description']))
                    <meta property="og:description" content="{{ $centralSeo['description'] }}">
                @endif
                @if (!empty($centralSeo['ogImage']))
                    <meta property="og:image" content="{{ $centralSeo['ogImage'] }}">
                @endif

                <meta name="twitter:card" content="{{ !empty($centralSeo['ogImage']) ? 'summary_large_image' : 'summary' }}">
                @if (!empty($centralSeo['title']))
                    <meta name="twitter:title" content="{{ $centralSeo['title'] }}">
                @endif
                @if (!empty($centralSeo['twitterDescription']))
                    <meta name="twitter:description" content="{{ $centralSeo['twitterDescription'] }}">
                @elseif (!empty($centralSeo['description']))
                    <meta name="twitter:description" content="{{ $centralSeo['description'] }}">
                @endif
                @if (!empty($centralSeo['ogImage']))
                    <meta name="twitter:image" content="{{ $centralSeo['ogImage'] }}">
                @endif

                @if (!empty($centralSeo['jsonLd']))
                    <script type="application/ld+json">{!! $centralSeo['jsonLd'] !!}</script>
                @endif
            @endif
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
        @if ($marketingFirstPaint)
            <style id="marketing-fp-critical">
                body.marketing-fp-pending #app {
                    position: fixed;
                    inset: 0;
                    visibility: hidden;
                    pointer-events: none;
                    z-index: -1;
                }

                body.marketing-fp-ready #marketing-first-paint > header,
                body.marketing-fp-ready #marketing-first-paint > div:first-of-type {
                    display: none;
                }
            </style>
        @endif
        @php
            $manifestPath = public_path('build/manifest.json');
            $viteManifest = [];

            $manifestMtime = file_exists($manifestPath) ? (int) filemtime($manifestPath) : 0;

            $viteManifest = $manifestMtime
                ? Cache::store('central')->remember(
                    "vite.manifest.{$manifestMtime}",
                    3600,
                    function () use ($manifestPath) {
                        $raw = file_get_contents($manifestPath);
                        $decoded = json_decode($raw ?: '', true);
                        return is_array($decoded) ? $decoded : [];
                    }
                )
                : [];

            $useViteDev = app()->environment('local');
            $entryKey = $isMarketingPage ? 'resources/js/marketing.js' : 'resources/js/app.js';
            $appEntryFile = $viteManifest[$entryKey]['file'] ?? null;
            $appImports = $viteManifest[$entryKey]['imports'] ?? [];
            $appCssFiles = $viteManifest[$entryKey]['css'] ?? [];
            $mainCssFile = $viteManifest['resources/css/app.css']['file'] ?? null;

            $marketingPageKey = $isMarketingPage ? MarketingSeoContext::pageKey(request()) : null;
            $dynamicPageManifestKey = $isMarketingPage ? match (true) {
                $marketingPageKey === 'home' => 'resources/js/Pages/Central/Home.vue',
                $marketingPageKey === 'login' => 'resources/js/Pages/Central/Login.vue',
                $marketingPageKey === 'register' => 'resources/js/Pages/Central/Register.vue',
                $marketingPageKey === 'features_index' => 'resources/js/Pages/Central/FeaturesIndex.vue',
                $marketingPageKey === 'compare_index' => 'resources/js/Pages/Central/CompareIndex.vue',
                $marketingPageKey === 'migrate_index' => 'resources/js/Pages/Central/MigrateIndex.vue',
                $marketingPageKey === 'static_integrations' => 'resources/js/Pages/Central/IntegrationsIndex.vue',
                $marketingPageKey === 'blog' => 'resources/js/Pages/Central/Blog/Index.vue',
                $marketingPageKey === 'static_contact' => 'resources/js/Pages/Central/Contact.vue',
                str_starts_with((string) $marketingPageKey, 'blog_') => 'resources/js/Pages/Central/Blog/Show.vue',
                str_starts_with((string) $marketingPageKey, 'static_') => 'resources/js/Pages/Central/MarketingStaticPage.vue',
                str_starts_with((string) $marketingPageKey, 'vertical_') => 'resources/js/Pages/Central/VerticalLanding.vue',
                str_starts_with((string) $marketingPageKey, 'feature_') => 'resources/js/Pages/Central/FeatureLanding.vue',
                str_starts_with((string) $marketingPageKey, 'integration_') => 'resources/js/Pages/Central/IntegrationLanding.vue',
                str_starts_with((string) $marketingPageKey, 'compare_') => 'resources/js/Pages/Central/CompetitorComparison.vue',
                str_starts_with((string) $marketingPageKey, 'migrate_') => 'resources/js/Pages/Central/MigrateLanding.vue',
                default => null,
            } : null;

            $dynamicPageFile = $dynamicPageManifestKey ? ($viteManifest[$dynamicPageManifestKey]['file'] ?? null) : null;

            $cssFiles = array_values(array_filter(array_merge(
                $mainCssFile ? [$mainCssFile] : [],
                is_array($appCssFiles) ? $appCssFiles : []
            )));
        @endphp

        @if ($useViteDev || ! $isMarketingPage || empty($viteManifest) || empty($appEntryFile))
            @vite($isMarketingPage ? ['resources/css/app.css', 'resources/js/marketing.js'] : ['resources/css/app.css', 'resources/js/app.js'])
        @else
            @if ($dynamicPageFile)
                <link rel="modulepreload" href="{{ asset('build/' . $dynamicPageFile) }}">
            @endif

            @foreach ($cssFiles as $cssFile)
                @php $cssHref = asset('build/' . $cssFile); @endphp
                <link rel="preload" as="style" href="{{ $cssHref }}">
                <link rel="stylesheet" href="{{ $cssHref }}" media="print" onload="this.media='all'">
                <noscript><link rel="stylesheet" href="{{ $cssHref }}"></noscript>
            @endforeach

            <link rel="modulepreload" as="script" href="{{ asset('build/' . $appEntryFile) }}">

            @foreach ($appImports as $importKey)
                @php $importFile = $viteManifest[$importKey]['file'] ?? null; @endphp
                @if ($importFile)
                    <link rel="modulepreload" as="script" href="{{ asset('build/' . $importFile) }}">
                @endif
            @endforeach

            <script type="module" src="{{ asset('build/' . $appEntryFile) }}"></script>
        @endif
        @inertiaHead
    </head>
    <body @class([
        'font-sans antialiased bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100',
        'marketing-fp-pending' => $marketingFirstPaint,
    ])>
        @if ($marketingFirstPaint)
            @include('partials.marketing-first-paint', ['shell' => $marketingFirstPaint])
        @endif
        @inertia
    </body>
</html>
