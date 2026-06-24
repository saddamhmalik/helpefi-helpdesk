<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PortalDeflectionBot from '../Components/PortalDeflectionBot.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
import { usePortalRoutes } from '../composables/usePortalRoutes.js';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isCustomer = computed(() => user.value?.is_customer);
const locales = computed(() => page.props.portalLocales ?? []);
const currentLocale = computed(() => page.props.portalLocale ?? 'en');
const mobileNavOpen = ref(false);
const { brand, portalPath } = usePortalRoutes();

const headerStyle = computed(() => {
    const styles = {};

    if (brand.value.primary_color) {
        styles.borderBottomColor = brand.value.primary_color;
    }

    return styles;
});

const titleStyle = computed(() => {
    if (!brand.value.primary_color) {
        return {};
    }

    return { color: brand.value.primary_color };
});

const logout = () => {
    mobileNavOpen.value = false;
    router.post(portalPath('/logout'));
};

const switchLocale = (code) => {
    const url = new URL(page.url, window.location.origin);
    url.searchParams.set('lang', code);
    router.get(`${url.pathname}${url.search}`, {}, { preserveState: true, replace: true });
};

const closeMobileNav = () => {
    mobileNavOpen.value = false;
};

const navLinks = computed(() => {
    const links = [
        { href: portalPath('/search'), label: 'layouts.portal.search' },
        { href: portalPath('/services'), label: 'layouts.portal.services' },
        { href: portalPath('/submit'), label: 'layouts.portal.submit_request' },
    ];

    if (isCustomer.value) {
        links.push({ href: portalPath('/my-tickets'), label: 'layouts.portal.my_tickets' });
        links.push({ action: 'logout', label: 'layouts.portal.logout' });
    } else {
        links.push({ href: portalPath('/track'), label: 'layouts.portal.track_request' });
        links.push({ href: portalPath('/login'), label: 'layouts.portal.sign_in' });
        links.push({ href: portalPath('/register'), label: 'layouts.portal.register', primary: true });
    }

    links.push({ href: '/login', label: 'layouts.portal.agent_login', external: true });

    return links;
});
</script>

<template>
    <a href="#main-content" class="agent-skip-link">{{ $t('components.skip_to_main_content') }}</a>

    <div class="min-h-screen agent-page-bg">
        <Transition name="slide-over">
            <div v-if="mobileNavOpen" class="fixed inset-0 z-40 md:hidden">
                <div class="absolute inset-0 bg-slate-900/40 transition-ui" @click="closeMobileNav" />
                <aside class="slide-over-panel absolute inset-y-0 right-0 flex w-[min(100%,20rem)] flex-col agent-panel shadow-xl">
                    <div class="flex items-center justify-between border-b agent-border px-4 py-4">
                        <p class="text-base font-semibold agent-text" :style="titleStyle">
                            {{ brand.portal_title || $t('layouts.portal.help_center') }}
                        </p>
                        <button
                            type="button"
                            class="rounded-lg p-2 agent-text-subtle agent-hover-surface"
                            :aria-label="$t('layouts.portal.close_menu')"
                            @click="closeMobileNav"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div v-if="locales.length > 1" class="border-b agent-border-subtle px-4 py-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide agent-text-subtle">{{ $t('layouts.portal.language') }}</p>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="locale in locales"
                                :key="locale.code"
                                type="button"
                                class="rounded-md px-2.5 py-1.5 text-xs font-medium transition"
                                :class="locale.code === currentLocale ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'agent-text-muted agent-hover-surface'"
                                @click="switchLocale(locale.code)"
                            >
                                {{ locale.code.toUpperCase() }}
                            </button>
                        </div>
                    </div>

                    <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                        <template v-for="(link, index) in navLinks" :key="`${link.label}-${index}`">
                            <button
                                v-if="link.action === 'logout'"
                                type="button"
                                class="block w-full rounded-xl px-3 py-2.5 text-left text-sm font-medium agent-text-muted transition agent-hover-surface"
                                @click="logout"
                            >
                                {{ $t(link.label) }}
                            </button>
                            <Link
                                v-else
                                :href="link.href"
                                class="block rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :class="link.primary ? 'bg-blue-600 text-white hover:bg-blue-700' : 'agent-text-muted agent-hover-surface'"
                                @click="closeMobileNav"
                            >
                                {{ $t(link.label) }}
                            </Link>
                        </template>
                    </nav>

                    <div class="border-t agent-border-subtle p-4">
                        <ThemeToggle persist="auto" />
                    </div>
                </aside>
            </div>
        </Transition>

        <header class="border-b agent-border agent-panel" :style="headerStyle">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <Link :href="portalPath()" class="min-w-0 text-lg font-semibold agent-text" :style="titleStyle">
                    {{ brand.portal_title || $t('layouts.portal.help_center') }}
                </Link>

                <nav class="hidden flex-wrap items-center gap-3 text-sm md:flex sm:gap-4">
                    <ThemeToggle persist="auto" />
                    <div v-if="locales.length > 1" class="flex items-center gap-1 rounded-lg border agent-border p-0.5">
                        <button
                            v-for="locale in locales"
                            :key="locale.code"
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium transition"
                            :class="locale.code === currentLocale ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : 'agent-text-muted agent-hover-surface'"
                            @click="switchLocale(locale.code)"
                        >
                            {{ locale.code.toUpperCase() }}
                        </button>
                    </div>
                    <Link :href="portalPath('/search')" class="agent-text-muted transition hover:text-slate-900 dark:hover:text-slate-100">{{ $t('layouts.portal.search') }}</Link>
                    <Link :href="portalPath('/services')" class="agent-text-muted transition hover:text-slate-900 dark:hover:text-slate-100">{{ $t('layouts.portal.services') }}</Link>
                    <Link :href="portalPath('/submit')" class="agent-text-muted transition hover:text-slate-900 dark:hover:text-slate-100">{{ $t('layouts.portal.submit_request') }}</Link>
                    <template v-if="isCustomer">
                        <Link :href="portalPath('/my-tickets')" class="agent-text-muted transition hover:text-slate-900 dark:hover:text-slate-100">{{ $t('layouts.portal.my_tickets') }}</Link>
                        <button type="button" class="agent-text-muted transition hover:text-slate-900 dark:hover:text-slate-100" @click="logout">{{ $t('layouts.portal.logout') }}</button>
                    </template>
                    <template v-else>
                        <Link :href="portalPath('/track')" class="agent-text-muted transition hover:text-slate-900 dark:hover:text-slate-100">{{ $t('layouts.portal.track_request') }}</Link>
                        <Link :href="portalPath('/login')" class="agent-text-muted transition hover:text-slate-900 dark:hover:text-slate-100">{{ $t('layouts.portal.sign_in') }}</Link>
                        <Link :href="portalPath('/register')" class="rounded-lg bg-blue-600 px-3 py-1.5 text-white hover:bg-blue-700">{{ $t('layouts.portal.register') }}</Link>
                    </template>
                    <Link href="/login" class="text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:hover:text-blue-300">{{ $t('layouts.portal.agent_login') }}</Link>
                </nav>

                <div class="flex items-center gap-2 md:hidden">
                    <ThemeToggle persist="auto" />
                    <button
                        type="button"
                        class="inline-flex rounded-lg border agent-border p-2 agent-text-muted transition agent-hover-surface"
                        :aria-label="$t('layouts.portal.open_menu')"
                        @click="mobileNavOpen = true"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </header>

        <main id="main-content" class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <slot />
        </main>

        <PortalDeflectionBot />
    </div>
</template>
