@php
    $page = $page ?? [];
@endphp
<section
    class="relative overflow-hidden text-white"
    style="background:#020617;min-height:32rem"
>
    <div class="relative mx-auto max-w-7xl px-4 pb-16 pt-10 sm:px-6 sm:pb-20 sm:pt-14 lg:px-8 lg:pb-28 lg:pt-20">
        <div class="max-w-xl lg:max-w-none">
            <h1 class="mt-6 text-3xl font-extrabold leading-[1.08] tracking-tight sm:mt-8 sm:text-[2.75rem] sm:leading-[1.05] lg:text-5xl xl:text-[3.5rem]">
                {{ $page['h1'] ?? '' }}
                @if (!empty($page['h1Highlight']))
                    <span class="mt-1 block bg-gradient-to-r from-blue-400 via-indigo-300 to-violet-400 bg-clip-text text-transparent">
                        {{ $page['h1Highlight'] }}
                    </span>
                @endif
            </h1>

            @if (!empty($page['subtitle']))
                <p class="mt-5 text-base leading-relaxed sm:mt-6 sm:text-lg lg:text-xl" style="color:#cbd5e1">
                    {{ $page['subtitle'] }}
                </p>
            @endif

            <div class="mt-7 flex flex-col gap-3 sm:mt-9 sm:flex-row sm:items-center">
                <a
                    href="/register"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-2xl shadow-blue-600/40 sm:px-8 sm:py-4 sm:text-base"
                >
                    {{ $page['ctaPrimary'] ?? 'Start free trial' }}
                </a>
                <a
                    href="{{ $page['ctaSecondaryHref'] ?? '#ai' }}"
                    class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/20 bg-white/5 px-6 py-4 text-sm font-semibold text-white backdrop-blur"
                >
                    {{ is_string($page['ctaSecondary'] ?? null) ? $page['ctaSecondary'] : 'Try AI demo' }}
                </a>
            </div>
        </div>
    </div>
</section>
