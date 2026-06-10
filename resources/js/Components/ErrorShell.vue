<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    status: {
        type: Number,
        required: true,
    },
    variant: {
        type: String,
        default: 'generic',
    },
});

const { t } = useI18n();
const page = usePage();

const portalBrand = computed(() => page.props.portalBrand);
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));

const homeHref = computed(() => {
    if (portalBrand.value?.slug) {
        return `/portal/${portalBrand.value.slug}`;
    }

    if (isAuthenticated.value) {
        return '/dashboard';
    }

    return '/login';
});

const homeLabel = computed(() => {
    if (portalBrand.value?.slug) {
        return t('errors.back_to_help_center');
    }

    if (isAuthenticated.value) {
        return t('errors.back_to_dashboard');
    }

    return t('errors.back_to_sign_in');
});

const title = computed(() => {
    if (props.variant === 'not_found') {
        return t('errors.not_found_title');
    }

    if (props.variant === 'server') {
        return t('errors.server_title');
    }

    return t(`errors.status_${props.status}_title`, t('errors.generic_title'));
});

const description = computed(() => {
    if (props.variant === 'not_found') {
        return t('errors.not_found_description');
    }

    if (props.variant === 'server') {
        return t('errors.server_description');
    }

    return t(`errors.status_${props.status}_description`, t('errors.generic_description'));
});

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }

    router.visit(homeHref.value);
}

function retry() {
    router.reload();
}
</script>

<template>
    <Head :title="title" />
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-4 py-16">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.18),_transparent_55%),radial-gradient(circle_at_bottom_right,_rgba(14,165,233,0.12),_transparent_40%)]"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -left-24 top-16 h-72 w-72 rounded-full bg-blue-500/10 blur-3xl"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -right-16 bottom-10 h-64 w-64 rounded-full bg-cyan-400/10 blur-3xl"
            aria-hidden="true"
        />

        <div class="relative w-full max-w-lg">
            <div class="rounded-3xl border border-white/10 bg-white/95 p-8 shadow-2xl shadow-slate-950/30 backdrop-blur sm:p-10">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-600 to-cyan-500 text-white shadow-lg shadow-blue-600/30">
                    <svg v-if="variant === 'not_found'" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                    </svg>
                    <svg v-else-if="variant === 'server'" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 5c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z" />
                    </svg>
                    <svg v-else class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>

                <p class="mt-6 text-center text-sm font-semibold uppercase tracking-[0.2em] text-blue-600">
                    {{ t('errors.error_label') }} {{ status }}
                </p>
                <h1 class="mt-3 text-center text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl">
                    {{ title }}
                </h1>
                <p class="mt-3 text-center text-sm leading-6 text-slate-600 sm:text-base">
                    {{ description }}
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        @click="goBack"
                    >
                        {{ t('errors.go_back') }}
                    </button>
                    <button
                        v-if="variant === 'server'"
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                        @click="retry"
                    >
                        {{ t('errors.try_again') }}
                    </button>
                    <Link
                        :href="homeHref"
                        class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700"
                        :class="{ 'sm:order-first': variant !== 'server' }"
                    >
                        {{ homeLabel }}
                    </Link>
                </div>
            </div>

            <p class="mt-6 text-center text-xs text-slate-400">
                {{ t('errors.support_hint') }}
            </p>
        </div>
    </div>
</template>
