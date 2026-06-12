<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PortalDeflectionBot from '../Components/PortalDeflectionBot.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
import { usePortalRoutes } from '../composables/usePortalRoutes.js';

const page = usePage();
const user = computed(() => page.props.auth.user);
const isCustomer = computed(() => user.value?.is_customer);
const locales = computed(() => page.props.portalLocales ?? []);
const currentLocale = computed(() => page.props.portalLocale ?? 'en');
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

const logout = () => router.post(portalPath('/logout'));

const switchLocale = (code) => {
    const url = new URL(page.url, window.location.origin);
    url.searchParams.set('lang', code);
    router.get(`${url.pathname}${url.search}`, {}, { preserveState: true, replace: true });
};
</script>

<template>
    <div class="min-h-screen agent-page-bg">
        <header class="border-b agent-border agent-panel" :style="headerStyle">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <Link :href="portalPath()" class="text-lg font-semibold agent-text" :style="titleStyle">
                    {{ brand.portal_title || $t('layouts.portal.help_center') }}
                </Link>
                <nav class="flex flex-wrap items-center gap-3 text-sm sm:gap-4">
                    <ThemeToggle persist="auto" />
                    <div v-if="locales.length > 1" class="flex items-center gap-1 rounded-lg border agent-border p-0.5">
                        <button
                            v-for="locale in locales"
                            :key="locale.code"
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium transition"
                            :class="locale.code === currentLocale ? 'bg-slate-900 text-white dark:bg-slate-100 dark:bg-slate-900 dark:text-slate-900 dark:text-slate-100' : 'agent-text-muted agent-hover-surface'"
                            @click="switchLocale(locale.code)"
                        >
                            {{ locale.code.toUpperCase() }}
                        </button>
                    </div>
                    <Link :href="portalPath('/search')" class="agent-text-muted transition hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100">{{ $t('layouts.portal.search') }}</Link>
                    <Link :href="portalPath('/services')" class="agent-text-muted transition hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100">{{ $t('layouts.portal.services') }}</Link>
                    <Link :href="portalPath('/submit')" class="agent-text-muted transition hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100">{{ $t('layouts.portal.submit_request') }}</Link>
                    <template v-if="isCustomer">
                        <Link :href="portalPath('/my-tickets')" class="agent-text-muted transition hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100">{{ $t('layouts.portal.my_tickets') }}</Link>
                        <button type="button" class="agent-text-muted transition hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100" @click="logout">{{ $t('layouts.portal.logout') }}</button>
                    </template>
                    <template v-else>
                        <Link :href="portalPath('/track')" class="agent-text-muted transition hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100">{{ $t('layouts.portal.track_request') }}</Link>
                        <Link :href="portalPath('/login')" class="agent-text-muted transition hover:text-slate-900 dark:text-slate-100 dark:hover:text-slate-100">{{ $t('layouts.portal.sign_in') }}</Link>
                        <Link :href="portalPath('/register')" class="rounded-lg bg-blue-600 px-3 py-1.5 text-white hover:bg-blue-700">{{ $t('layouts.portal.register') }}</Link>
                    </template>
                    <Link href="/login" class="text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:text-blue-400 dark:hover:text-blue-300">{{ $t('layouts.portal.agent_login') }}</Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
            <slot />
        </main>

        <PortalDeflectionBot />
    </div>
</template>
