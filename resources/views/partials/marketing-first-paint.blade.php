@php
    $page = $shell['page'] ?? [];
    $layout = $shell['layout'] ?? [];
    $nav = $shell['nav'] ?? [];
    $pageType = $page['type'] ?? 'landing';
@endphp
<div
    id="marketing-first-paint"
    class="flex min-h-screen flex-col overflow-x-hidden font-sans antialiased"
>
    @if (($shell['trialDays'] ?? 0) > 0)
        <div
            class="relative z-50 px-4 py-2.5 text-center text-xs font-medium text-white sm:text-sm"
            style="background:linear-gradient(to right,#2563eb,#4f46e5,#7c3aed)"
        >
            <div class="mx-auto flex max-w-4xl flex-col items-center justify-center gap-1 sm:flex-row sm:gap-2">
                <span>{{ $shell['promoTrial'] }}</span>
                <a href="/register" class="font-bold underline underline-offset-2 hover:text-blue-100">{{ $shell['promoStart'] }}</a>
            </div>
        </div>
    @endif

    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur-md">
        <div class="mx-auto flex h-14 min-w-0 max-w-7xl items-center justify-between gap-3 px-4 sm:h-16 sm:px-6 lg:px-8">
            <a href="/" class="flex min-w-0 shrink items-center">
                <img src="/logo.png" alt="Helpefi" width="120" height="32" class="h-8 w-auto" fetchpriority="high" decoding="async">
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
                @foreach ($nav['hubs'] ?? [] as $hubLink)
                    <a href="{{ $hubLink['href'] }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900">{{ $hubLink['label'] }}</a>
                @endforeach
            </nav>

            <div class="hidden items-center gap-2 sm:flex">
                <a href="/login" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900">
                    {{ $layout['sign_in'] ?? 'Sign in' }}
                </a>
                <a
                    href="/register"
                    class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-blue-600/30 transition hover:from-blue-500 hover:to-indigo-500"
                >
                    {{ $layout['start_free_trial'] ?? 'Start free trial' }}
                </a>
            </div>

            <a href="/register" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm sm:hidden">Try free</a>
        </div>
    </header>

    <main id="main-content" class="flex-1 overflow-x-hidden bg-slate-50 text-slate-900">
        @if (!empty($page['breadcrumbs']))
            <nav class="border-b border-slate-200 bg-white px-4 py-3 text-sm text-slate-500 sm:px-6 lg:px-8" aria-label="Breadcrumb">
                <ol class="mx-auto flex max-w-7xl flex-wrap items-center gap-1">
                    @foreach ($page['breadcrumbs'] as $index => $crumb)
                        <li class="inline-flex items-center gap-1">
                            @if ($index > 0)
                                <span aria-hidden="true" class="text-slate-300">/</span>
                            @endif
                            @if ($index === count($page['breadcrumbs']) - 1)
                                <span class="font-medium text-slate-700" aria-current="page">{{ $crumb['label'] }}</span>
                            @else
                                <a href="{{ $crumb['href'] }}" class="hover:text-slate-900">{{ $crumb['label'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        @if ($pageType === 'home')
            @include('partials.marketing-first-paint-home', ['page' => $page])
        @else
            <section class="bg-slate-950 py-12 text-white sm:py-16">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    @if (!empty($page['badge']))
                        <p class="text-xs font-semibold uppercase tracking-wider text-violet-300">{{ $page['badge'] }}</p>
                    @endif
                    <h1 class="mt-4 max-w-3xl text-3xl font-extrabold tracking-tight sm:text-5xl">
                        {{ $page['h1'] ?? '' }}
                        @if (!empty($page['h1Highlight']))
                            <span class="mt-2 block bg-gradient-to-r from-violet-400 via-blue-300 to-cyan-300 bg-clip-text text-transparent">{{ $page['h1Highlight'] }}</span>
                        @endif
                    </h1>
                    @if (!empty($page['subtitle']))
                        <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">{{ $page['subtitle'] }}</p>
                    @endif
                    <div class="mt-8 flex flex-wrap gap-4">
                        <a href="/register" class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-lg">Start free trial</a>
                        <a href="/pricing" class="rounded-xl border border-white/20 px-6 py-3 text-sm font-semibold text-white hover:bg-white/10">View pricing</a>
                    </div>
                </div>
            </section>

            @if (!empty($page['updatedAt']) || !empty($page['author']) || !empty($page['reviewer']))
                <section class="border-b border-slate-200 bg-white">
                    <div class="mx-auto max-w-3xl px-4 py-4 sm:px-6 lg:px-8">
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-slate-500">
                            @if (!empty($page['updatedAt']))
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Last updated on {{ $page['updatedAt'] }}
                                </span>
                            @endif
                            @if (!empty($page['author']['name']))
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    Written by {{ $page['author']['name'] }}
                                    @if (!empty($page['author']['role']))
                                        <span class="hidden sm:inline text-slate-400">· {{ $page['author']['role'] }}</span>
                                    @endif
                                </span>
                            @endif
                            @if (!empty($page['reviewer']['name']))
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    Reviewed by {{ $page['reviewer']['name'] }}
                                    @if (!empty($page['reviewer']['role']))
                                        <span class="hidden sm:inline text-slate-400">· {{ $page['reviewer']['role'] }}</span>
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            @if ($pageType === 'article' && !empty($page['body']))
                <section class="bg-white py-10">
                    <div class="mx-auto max-w-3xl px-4 text-base leading-relaxed text-slate-700 sm:px-6 lg:px-8">
                        <p>{{ \Illuminate\Support\Str::limit($page['body'], 4000) }}</p>
                    </div>
                </section>
            @endif

            @if (!empty($page['sections']))
                @php
                    $sectionCount = count($page['sections']);
                    $longFormCompare = $sectionCount >= 8;
                @endphp
                <section class="bg-white py-12 sm:py-16">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        @if ($longFormCompare)
                            <div class="mx-auto max-w-3xl space-y-10">
                                @foreach ($page['sections'] as $section)
                                    <article>
                                        @if (!empty($section['title']))
                                            <h2 class="text-xl font-semibold text-slate-900">{{ $section['title'] }}</h2>
                                        @endif
                                        @if (!empty($section['body']))
                                            <p class="mt-3 text-base leading-relaxed text-slate-700">{{ $section['body'] }}</p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @else
                            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($page['sections'] as $section)
                                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-6">
                                        @if (!empty($section['title']))
                                            <h2 class="text-lg font-semibold text-slate-900">{{ $section['title'] }}</h2>
                                        @endif
                                        @if (!empty($section['body']))
                                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $section['body'] }}</p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if (!empty($page['links']))
                <section class="bg-slate-50 py-12 sm:py-16" style="content-visibility:auto">
                    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <h2 class="text-2xl font-bold text-slate-900">Explore</h2>
                        <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($page['links'] as $link)
                                <li>
                                    <a href="{{ $link['href'] }}" class="block rounded-xl border border-slate-200 bg-white p-5 transition hover:border-blue-300 hover:shadow-sm">
                                        <span class="font-semibold text-slate-900">{{ $link['label'] }}</span>
                                        @if (!empty($link['description']))
                                            <span class="mt-2 block text-sm text-slate-600">{{ $link['description'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif

            @if (!empty($page['faqs']))
                <section class="bg-white py-12 sm:py-16" style="content-visibility:auto">
                    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                        <h2 class="text-2xl font-bold text-slate-900">Frequently asked questions</h2>
                        <dl class="mt-8 space-y-6">
                            @foreach ($page['faqs'] as $faq)
                                <div>
                                    <dt class="text-lg font-semibold text-slate-900">{{ $faq['q'] }}</dt>
                                    <dd class="mt-2 text-slate-600">{{ $faq['a'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                </section>
            @endif

            @if (!empty($page['externalReferences']))
                <section class="border-t border-slate-200 bg-slate-50 py-12" style="content-visibility:auto">
                    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                        <h2 class="text-lg font-semibold text-slate-900">References & sources</h2>
                        <ul class="mt-4 space-y-4">
                            @foreach ($page['externalReferences'] as $ref)
                                <li class="rounded-xl border border-slate-200 bg-white p-4">
                                    <a href="{{ $ref['url'] }}" target="_blank" rel="noopener noreferrer" class="font-medium text-blue-600 hover:text-blue-800">
                                        {{ $ref['title'] }} →
                                    </a>
                                    @if (!empty($ref['description']))
                                        <p class="mt-1 text-sm text-slate-600">{{ $ref['description'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            @endif

            @if (!empty($page['ctaTitle']) || !empty($page['ctaBody']))
                <section class="border-t border-slate-200 bg-white py-12" style="content-visibility:auto">
                    <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                        @if (!empty($page['ctaTitle']))
                            <h2 class="text-2xl font-bold text-slate-900">{{ $page['ctaTitle'] }}</h2>
                        @endif
                        @if (!empty($page['ctaBody']))
                            <p class="mt-3 text-slate-600">{{ $page['ctaBody'] }}</p>
                        @endif
                        <a href="/register" class="mt-6 inline-flex rounded-xl bg-blue-600 px-6 py-3 text-sm font-bold text-white">Start free trial</a>
                    </div>
                </section>
            @endif
        @endif
    </main>

    <footer class="border-t border-slate-800 bg-slate-950 text-slate-300" style="content-visibility:auto">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $layout['footer_product'] ?? 'Product' }}</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li><a href="/features" class="hover:text-white">Features</a></li>
                        <li><a href="/pricing" class="hover:text-white">Pricing</a></li>
                        <li><a href="/integrations" class="hover:text-white">Integrations</a></li>
                    </ul>
                </div>
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $layout['footer_features'] ?? 'Features' }}</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach (array_slice($nav['features'] ?? [], 0, 8) as $feature)
                            <li><a href="{{ $feature['path'] }}" class="hover:text-white">{{ $feature['nav_label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $layout['footer_solutions'] ?? 'Solutions' }}</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach (array_slice($nav['verticals'] ?? [], 0, 8) as $vertical)
                            <li><a href="{{ $vertical['path'] }}" class="hover:text-white">{{ $vertical['nav_label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $layout['footer_compare'] ?? 'Compare' }}</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($nav['comparisons'] ?? [] as $compare)
                            <li><a href="{{ $compare['path'] }}" class="hover:text-white">{{ $compare['footer_label'] ?? $compare['nav_label'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $layout['footer_company'] ?? 'Company' }}</h2>
                    <ul class="mt-4 space-y-2 text-sm">
                        <li><a href="/about" class="hover:text-white">About</a></li>
                        <li><a href="/blog" class="hover:text-white">Blog</a></li>
                        <li><a href="/contact" class="hover:text-white">Contact</a></li>
                        <li><a href="/privacy" class="hover:text-white">Privacy</a></li>
                        <li><a href="/terms" class="hover:text-white">Terms</a></li>
                    </ul>
                </div>
            </div>
            <p class="mt-8 border-t border-white/10 pt-6 text-sm text-slate-500">
                {{ $layout['footer_tagline'] ?? 'Modern AI helpdesk for support and IT teams.' }}
            </p>
        </div>
    </footer>
</div>
