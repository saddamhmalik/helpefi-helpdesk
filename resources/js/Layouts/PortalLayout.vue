<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import PortalDeflectionBot from '../Components/PortalDeflectionBot.vue';
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
    <div class="min-h-screen bg-slate-50">
        <header class="border-b border-slate-200 bg-white" :style="headerStyle">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <Link :href="portalPath()" class="text-lg font-semibold text-slate-900" :style="titleStyle">
                    {{ brand.portal_title || $t('layouts.portal.help_center') }}
                </Link>
                <nav class="flex items-center gap-4 text-sm">
                    <div v-if="locales.length > 1" class="flex items-center gap-1 rounded-lg border border-slate-200 p-0.5">
                        <button
                            v-for="locale in locales"
                            :key="locale.code"
                            type="button"
                            class="rounded-md px-2 py-1 text-xs font-medium transition"
                            :class="locale.code === currentLocale ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-50'"
                            @click="switchLocale(locale.code)"
                        >
                            {{ locale.code.toUpperCase() }}
                        </button>
                    </div>
                    <Link :href="portalPath('/search')" class="text-slate-600 hover:text-slate-900">{{ $t('layouts.portal.search') }}</Link>
                    <Link :href="portalPath('/services')" class="text-slate-600 hover:text-slate-900">{{ $t('layouts.portal.services') }}</Link>
                    <Link :href="portalPath('/submit')" class="text-slate-600 hover:text-slate-900">{{ $t('layouts.portal.submit_request') }}</Link>
                    <template v-if="isCustomer">
                        <Link :href="portalPath('/my-tickets')" class="text-slate-600 hover:text-slate-900">{{ $t('layouts.portal.my_tickets') }}</Link>
                        <button type="button" class="text-slate-600 hover:text-slate-900" @click="logout">{{ $t('layouts.portal.logout') }}</button>
                    </template>
                    <template v-else>
                        <Link :href="portalPath('/track')" class="text-slate-600 hover:text-slate-900">{{ $t('layouts.portal.track_request') }}</Link>
                        <Link :href="portalPath('/login')" class="text-slate-600 hover:text-slate-900">{{ $t('layouts.portal.sign_in') }}</Link>
                        <Link :href="portalPath('/register')" class="rounded-lg bg-blue-600 px-3 py-1.5 text-white hover:bg-blue-700">{{ $t('layouts.portal.register') }}</Link>
                    </template>
                    <Link href="/login" class="text-blue-600 hover:text-blue-700">{{ $t('layouts.portal.agent_login') }}</Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-8">
            <slot />
        </main>

        <PortalDeflectionBot />
    </div>
</template>
