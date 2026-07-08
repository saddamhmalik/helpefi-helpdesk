<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';
import AppLogo from '../Components/AppLogo.vue';
import SeoHead from '../Components/SeoHead.vue';
import { formatMarketingTemplate } from '../composables/useMarketingEnglish.js';
import { useBodyScrollLock } from '../composables/useModal.js';

const CentralMarketingHelpBot = defineAsyncComponent(() => import('../Components/CentralMarketingHelpBot.vue'));
const showHelpBot = ref(false);

onMounted(() => {
    const reveal = () => {
        showHelpBot.value = true;
    };

    if ('requestIdleCallback' in window) {
        requestIdleCallback(reveal, { timeout: 2500 });
    } else {
        setTimeout(reveal, 1500);
    }
});

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    showFooter: { type: Boolean, default: true },
    showPromoBar: { type: Boolean, default: true },
    minimalHeader: { type: Boolean, default: false },
    socialLinks: { type: Array, default: () => [] },
});

const page = usePage();
const mobileOpen = ref(false);

const chrome = computed(() => page.props.marketingChrome ?? {});
const widgets = computed(() => page.props.marketingWidgets ?? {});
const layout = computed(() => widgets.value.layout ?? chrome.value.layout ?? {});
const staticPages = computed(() => page.props.staticPages ?? []);
const verticalPages = computed(() => page.props.verticalPages ?? []);
const comparePages = computed(() => page.props.comparePages ?? []);
const migratePages = computed(() => page.props.migratePages ?? []);
const featurePages = computed(() => page.props.featurePages ?? []);
const parentCompany = computed(() => page.props.parentCompany ?? null);
const aiDemoEnabled = computed(() => page.props.aiDemoEnabled ?? true);

const layoutText = (key, params = {}) => formatMarketingTemplate(layout.value[key] ?? key, params);
const chromeText = (key, params = {}) => formatMarketingTemplate(chrome.value[key] ?? key, { brand: props.brand, days: props.trialDays, ...params });
const promoText = (key, params = {}) => formatMarketingTemplate(widgets.value.promo?.[key] ?? key, { days: props.trialDays, ...params });
const staticNavLabel = (slug) => staticPages.value.find((entry) => entry.slug === slug)?.nav_label ?? slug;
const blogText = (key, params = {}) => formatMarketingTemplate(chrome.value.blog?.[key] ?? key, { days: props.trialDays, ...params });

const compareLinkLabel = (compare) => (
    compare.footer_label
    || `vs ${compare.competitor_name || compare.nav_label || compare.slug}`
);

const socialIcons = {
    x: 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
    linkedin: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
    facebook: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
    instagram: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z',
    youtube: 'M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
    github: 'M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.91 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222 0 1.606-.014 2.898-.014 3.293 0 .322.216.694.825.576C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12z',
};
const iconPath = (key) => socialIcons[key] ?? '';

const navLinks = computed(() => [
    { href: '/#product', label: layoutText('product') },
    { href: '/#ai', label: layoutText('ai') },
    { href: '/#differentiators', label: layoutText('why_us') },
    { href: '/#features', label: layoutText('features') },
    { href: '/#how-it-works', label: layoutText('how_it_works') },
    { href: '/#pricing', label: layoutText('pricing') },
    { href: '/#faq', label: layoutText('faq') },
]);

useBodyScrollLock(mobileOpen);
</script>

<template>
    <SeoHead />
    <a href="#main-content" class="agent-skip-link">{{ chrome.skip_to_main_content ?? 'Skip to main content' }}</a>
    <div class="flex min-h-screen flex-col overflow-x-hidden">
        <div
            v-if="showPromoBar && trialDays"
            class="relative z-50 bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 px-4 py-2.5 text-center text-xs font-medium text-white sm:text-sm"
        >
            <div class="mx-auto flex max-w-4xl flex-col items-center justify-center gap-1 sm:flex-row sm:gap-2">
                <span>{{ promoText('trial') }}</span>
                <Link href="/register" class="font-bold underline underline-offset-2 hover:text-blue-100">
                    {{ promoText('start') }}
                </Link>
            </div>
        </div>
        <header class="sticky top-0 z-40 border-b agent-border bg-white/95 shadow-sm backdrop-blur-md dark:bg-slate-900/95">
            <div class="mx-auto flex h-14 min-w-0 max-w-7xl items-center justify-between gap-3 px-4 sm:h-16 sm:px-6 lg:px-8">
                <Link href="/" class="flex min-w-0 shrink items-center" aria-label="Go to homepage">
                    <AppLogo size="md" />
                </Link>

                <div v-if="minimalHeader" class="flex items-center gap-2">
                    <Link
                        href="/login"
                        prefetch
                        class="rounded-lg px-3 py-2 text-sm font-medium agent-text-muted transition hover:text-slate-900 dark:hover:text-slate-100"
                    >
                        {{ layoutText('sign_in') }}
                    </Link>
                </div>

                <nav v-else class="hidden items-center gap-1 lg:flex" aria-label="Main navigation">
                    <a
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="rounded-lg px-3 py-2 text-sm font-medium agent-text-muted transition hover:bg-slate-100 dark:bg-slate-900 hover:text-slate-900 dark:text-slate-100 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div v-if="!minimalHeader" class="hidden items-center gap-2 sm:flex">
                    <Link
                        href="/login"
                        prefetch
                        class="rounded-lg px-3 py-2 text-sm font-medium agent-text-muted transition hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100"
                    >
                        {{ layoutText('sign_in') }}
                    </Link>
                    <Link
                        href="/register"
                        prefetch
                        class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-blue-600/30 transition hover:from-blue-500 hover:to-indigo-500"
                    >
                        {{ layoutText('start_free_trial') }}
                    </Link>
                </div>

                <div v-if="!minimalHeader" class="flex items-center gap-2 lg:hidden">
                    <Link
                        href="/register"
                        prefetch
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm sm:hidden"
                    >
                        Try free
                    </Link>
                    <button
                        type="button"
                        class="rounded-lg p-2 agent-text"
                        :aria-expanded="mobileOpen"
                        aria-label="Toggle menu"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <svg v-if="!mobileOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg v-else class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <Transition name="fade">
                <div
                    v-if="mobileOpen"
                    class="fixed inset-0 top-14 z-30 bg-slate-900/20 backdrop-blur-[1px] sm:top-16 lg:hidden"
                    @click="mobileOpen = false"
                />
            </Transition>

            <Transition name="fade">
                <div
                    v-if="mobileOpen"
                    class="absolute left-0 right-0 z-40 max-h-[calc(100dvh-3.5rem)] overflow-y-auto border-t agent-border agent-panel px-4 py-4 shadow-lg sm:max-h-[calc(100dvh-4rem)] lg:hidden"
                >
                    <nav class="flex flex-col gap-1" aria-label="Mobile navigation">
                        <a
                            v-for="link in navLinks"
                            :key="link.href"
                            :href="link.href"
                            class="rounded-lg px-3 py-3 text-sm font-medium agent-text transition active:bg-slate-100 dark:bg-slate-900 dark:active:bg-slate-800"
                            @click="mobileOpen = false"
                        >
                            {{ link.label }}
                        </a>
                        <Link href="/login" prefetch class="rounded-lg px-3 py-3 text-sm font-medium agent-text-muted active:bg-slate-100 dark:bg-slate-900 dark:active:bg-slate-800" @click="mobileOpen = false">
                            {{ layoutText('sign_in') }}
                        </Link>
                        <Link href="/register" prefetch class="mt-2 rounded-xl bg-blue-600 px-3 py-3 text-center text-sm font-semibold text-white" @click="mobileOpen = false">
                            {{ layoutText('start_free_trial') }}
                        </Link>
                    </nav>
                </div>
            </Transition>
        </header>

        <main id="main-content" class="flex-1 overflow-x-hidden agent-page-bg">
            <slot />
        </main>

        <footer
            v-if="showFooter"
            class="border-t border-slate-200 bg-slate-950 text-slate-300 dark:border-slate-800"
            :class="aiDemoEnabled ? 'pb-20 sm:pb-0' : ''"
            style="content-visibility:auto"
        >
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
                <div class="flex flex-col gap-8 border-b border-white/10 pb-10 lg:flex-row lg:items-start lg:justify-between lg:gap-12">
                    <div class="max-w-md">
                        <AppLogo size="md" surface="light" />
                        <p class="mt-3 text-sm leading-relaxed text-slate-400 dark:text-slate-500">
                            {{ layoutText('footer_tagline') }}
                        </p>
                        <div v-if="socialLinks.length" class="mt-5 flex flex-wrap gap-2.5">
                            <a
                                v-for="social in socialLinks"
                                :key="social.key"
                                :href="social.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                :aria-label="social.label"
                                :title="social.label"
                                class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/5 text-slate-400 ring-1 ring-white/10 transition hover:bg-white/10 hover:text-white"
                            >
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path :d="iconPath(social.key)" /></svg>
                            </a>
                        </div>
                    </div>

                    <div class="w-full rounded-2xl border border-white/10 bg-gradient-to-br from-white/[0.07] to-white/[0.02] p-5 sm:p-6 lg:max-w-sm lg:shrink-0">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ layoutText('footer_get_started') }}</h2>
                        <Link
                            href="/register"
                            prefetch
                            class="mt-4 flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-900/30 transition hover:from-blue-500 hover:to-indigo-500"
                        >
                            {{ layoutText('start_free_trial') }}
                        </Link>
                        <div class="mt-4 flex flex-col gap-2.5 border-t border-white/10 pt-4 text-sm sm:flex-row sm:flex-wrap sm:gap-x-5 sm:gap-y-2">
                            <Link href="/login" prefetch class="text-slate-300 transition hover:text-white">
                                {{ layoutText('footer_sign_in_workspace') }}
                            </Link>
                            <a href="/#how-it-works" class="text-slate-400 transition hover:text-white">
                                {{ layoutText('how_it_works') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div class="grid gap-8 pt-10 sm:grid-cols-2 sm:gap-10 lg:grid-cols-6">
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ layoutText('footer_product') }}</h2>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li><a href="/#differentiators" class="transition hover:text-white">{{ layoutText('why_us') }}</a></li>
                        <li><Link href="/features" prefetch class="transition hover:text-white">{{ layoutText('features') }}</Link></li>
                        <li><a href="/#product" class="transition hover:text-white">{{ layoutText('footer_platform_overview') }}</a></li>
                            <li><a href="/#pricing" class="transition hover:text-white">{{ layoutText('pricing') }}</a></li>
                            <li><a href="/#faq" class="transition hover:text-white">{{ layoutText('faq') }}</a></li>
                        </ul>
                    </div>
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ layoutText('footer_features') }}</h2>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li v-for="feature in featurePages" :key="feature.slug">
                                <Link :href="feature.path" prefetch class="transition hover:text-white">
                                    {{ feature.nav_label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ layoutText('footer_solutions') }}</h2>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li v-for="vertical in verticalPages" :key="vertical.slug">
                                <Link :href="vertical.path" prefetch class="transition hover:text-white">
                                    {{ vertical.nav_label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ layoutText('footer_compare') }}</h2>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li v-for="compare in comparePages" :key="compare.slug">
                                <Link :href="compare.path" class="transition hover:text-white">
                                    {{ compareLinkLabel(compare) }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ layoutText('footer_migrate') }}</h2>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li v-for="migrate in migratePages" :key="migrate.slug">
                                <Link :href="migrate.path" class="transition hover:text-white">
                                    {{ migrate.source_name ?? migrate.nav_label }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ layoutText('footer_company') }}</h2>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li><Link href="/about" class="transition hover:text-white">{{ staticNavLabel('about') }}</Link></li>
                            <li><Link href="/contact" class="transition hover:text-white">{{ staticNavLabel('contact') }}</Link></li>
                            <li><Link href="/blog" class="transition hover:text-white">{{ blogText('index_nav_label') }}</Link></li>
                            <li><Link href="/pricing" class="transition hover:text-white">{{ staticNavLabel('pricing') }}</Link></li>
                            <li><Link href="/privacy" class="transition hover:text-white">{{ staticNavLabel('privacy') }}</Link></li>
                            <li><Link href="/terms" class="transition hover:text-white">{{ staticNavLabel('terms') }}</Link></li>
                        </ul>
                    </div>
                </div>
                <div
                    class="mt-8 flex flex-col items-start gap-2 border-t border-white/10 pt-6 sm:mt-12 sm:flex-row sm:items-center sm:justify-between sm:pt-8"
                    :class="aiDemoEnabled ? 'pr-[4.75rem] sm:pr-0' : ''"
                >
                    <p class="text-xs text-slate-500 dark:text-slate-400">© {{ new Date().getFullYear() }} {{ brand }}. {{ layoutText('footer_rights') }}</p>
                    <p v-if="parentCompany" class="text-xs text-slate-500 dark:text-slate-400 sm:text-right">
                        {{ layoutText('footer_product_of') }}
                        <a
                            :href="parentCompany.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-slate-300 underline decoration-white/20 underline-offset-2 transition hover:text-white hover:decoration-white/40"
                        >{{ parentCompany.name }}</a>
                    </p>
                </div>
            </div>
        </footer>

        <CentralMarketingHelpBot v-if="showHelpBot" :enabled="aiDemoEnabled" :brand="brand" />
    </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
