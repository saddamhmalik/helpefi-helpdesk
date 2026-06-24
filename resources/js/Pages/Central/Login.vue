<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import { formatMarketingTemplate, useMarketingEnglish } from '../../composables/useMarketingEnglish.js';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    centralDomain: { type: String, default: '' },
    prefillSlug: { type: String, default: '' },
    prefillEmail: { type: String, default: '' },
    seo: { type: Object, default: () => ({}) },
    marketingLabels: { type: Object, default: () => ({}) },
    registerContent: { type: Object, default: () => ({}) },
});

const page = usePage();
const platformName = computed(() => props.brand || 'helpefi');
const { label } = useMarketingEnglish(platformName, computed(() => page.props.marketingLabels ?? props.marketingLabels));

const registerCopy = computed(() => page.props.registerContent ?? props.registerContent ?? {});

const copy = (key, params = {}) => formatMarketingTemplate(registerCopy.value[key] ?? key, {
    days: props.trialDays,
    brand: props.brand,
    ...params,
});

const form = useForm({
    slug: props.prefillSlug ?? '',
    email: props.prefillEmail ?? '',
});

const fieldError = (field) => form.errors[field] ?? page.props.errors?.[field] ?? '';

const errorList = computed(() => Object.values(form.errors).length
    ? Object.entries(form.errors).map(([field, message]) => ({ field, message }))
    : Object.entries(page.props.errors ?? {}).map(([field, message]) => ({ field, message })));

const hasValidationErrors = computed(() => errorList.value.length > 0);

const centralDomain = computed(() => props.centralDomain || window.location.hostname);

const workspaceDomainSuffix = computed(() => {
    const domain = centralDomain.value;
    const port = window.location.port;
    const defaultPort = window.location.protocol === 'https:' ? '443' : '80';

    if (! port || port === defaultPort) {
        return domain;
    }

    return `${domain}:${port}`;
});

const slugValid = computed(() => /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(form.slug.trim()) && form.slug.trim() !== '');

const sidebarBenefits = computed(() => [
    copy('benefit_inbox'),
    copy('benefit_setup'),
    copy('benefit_trial'),
]);

const trustBadges = computed(() => [
    copy('trust_no_card'),
    copy('trust_cancel'),
    copy('trust_setup'),
]);

const submit = () => {
    form.post('/login', {
        preserveScroll: true,
        onError: () => window.scrollTo({ top: 0, behavior: 'smooth' }),
    });
};

const inputClass = (field) => {
    const base = 'w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:outline-none focus:ring-2 dark:bg-slate-900 dark:text-slate-100';
    const hasError = !!fieldError(field);

    if (hasError) {
        return `${base} border-red-300 focus:border-red-500 focus:ring-red-500/20 dark:border-red-900/60`;
    }

    return `${base} border-slate-200 focus:border-blue-500 focus:ring-blue-500/20 dark:border-slate-800`;
};
</script>

<template>
    <CentralLayout :brand="platformName" :trial-days="trialDays" :show-footer="false" :show-promo-bar="false" minimal-header>
        <div class="login-shell relative min-h-[calc(100dvh-3.5rem)] overflow-hidden bg-slate-50 dark:bg-slate-950 sm:min-h-[calc(100dvh-4rem)]">
            <div class="pointer-events-none absolute inset-0 login-grid opacity-[0.35]" />
            <div class="pointer-events-none absolute -left-32 top-0 h-96 w-96 rounded-full bg-blue-400/10 blur-3xl" />
            <div class="pointer-events-none absolute -right-24 bottom-0 h-80 w-80 rounded-full bg-violet-400/10 blur-3xl" />

            <div class="relative mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8 lg:py-10">
                <div class="login-card overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 lg:grid lg:grid-cols-2 lg:items-stretch">
                    <aside class="relative hidden overflow-hidden bg-slate-950 px-10 py-10 text-white lg:flex lg:h-full lg:min-h-full lg:flex-col">
                        <div class="pointer-events-none absolute inset-0">
                            <div class="absolute -right-20 top-0 h-72 w-72 rounded-full bg-blue-600/25 blur-3xl login-glow" />
                            <div class="absolute bottom-0 left-0 h-64 w-64 rounded-full bg-violet-500/20 blur-3xl login-glow-delayed" />
                        </div>

                        <div class="relative">
                            <Link href="/" class="inline-flex items-center gap-1.5 text-sm text-slate-400 transition hover:text-white">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                                {{ label('back_to_home') }}
                            </Link>
                            <div class="mt-6 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-medium text-blue-200 backdrop-blur-sm">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 login-pulse-dot" />
                                {{ label('sign_in') }}
                            </div>
                            <h1 class="mt-5 text-3xl font-semibold leading-tight tracking-tight">
                                {{ label('welcome_back') }}
                            </h1>
                            <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-400">
                                {{ label('enter_your_workspace_url_to_open_the_agent_login_for_your_team') }}
                            </p>
                        </div>

                        <div class="relative mt-8 flex flex-1 flex-col gap-6">
                            <ul class="mt-auto space-y-3">
                                <li
                                    v-for="item in sidebarBenefits"
                                    :key="item"
                                    class="flex items-center gap-3 text-sm text-slate-300"
                                >
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/20">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                    </span>
                                    {{ item }}
                                </li>
                            </ul>

                            <div class="rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                                <p class="text-xs font-medium text-slate-300">{{ label('new_to_brand') }}</p>
                                <p class="mt-1 text-sm text-white">{{ label('start_trial_no_card', { days: trialDays }) }}</p>
                                <Link href="/register" class="mt-3 inline-flex text-sm font-semibold text-blue-400 transition hover:text-blue-300">
                                    {{ label('create_workspace_link') }}
                                </Link>
                            </div>
                        </div>
                    </aside>

                    <div class="px-4 py-6 sm:px-8 sm:py-8 lg:py-10">
                        <div class="mx-auto w-full max-w-lg">
                            <div class="mb-6 lg:hidden">
                                <Link href="/" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">← {{ label('back_to_home') }}</Link>
                                <h1 class="mt-4 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ label('welcome_back') }}</h1>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ label('sign_in') }}</p>
                            </div>

                            <div v-if="hasValidationErrors" class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 dark:border-red-900/60 dark:bg-red-950/40">
                                <p class="text-sm font-semibold text-red-800 dark:text-red-300">{{ copy('fix_errors') }}</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                                    <li v-for="error in errorList" :key="error.field">{{ error.message }}</li>
                                </ul>
                            </div>

                            <form class="space-y-5" @submit.prevent="submit">
                                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-900/5 dark:border-slate-800 dark:bg-slate-900 dark:ring-white/5 sm:p-6">
                                    <div class="mb-5">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">{{ label('sign_in') }}</p>
                                        <h2 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ label('sign_in_to_your_workspace') }}</h2>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ label('well_send_you_to_your_teams_login_page') }}</p>
                                    </div>

                                    <div class="space-y-5">
                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label('workspace_url') }}</label>
                                            <div
                                                class="flex overflow-hidden rounded-xl border bg-white shadow-sm transition focus-within:ring-2 dark:bg-slate-900"
                                                :class="fieldError('slug')
                                                    ? 'border-red-300 focus-within:border-red-500 focus-within:ring-red-500/20 dark:border-red-900/60'
                                                    : slugValid
                                                        ? 'border-emerald-300 focus-within:border-emerald-500 focus-within:ring-emerald-500/20 dark:border-emerald-900/50'
                                                        : 'border-slate-200 focus-within:border-blue-500 focus-within:ring-blue-500/20 dark:border-slate-800'"
                                            >
                                                <span class="pointer-events-none flex items-center pl-3.5 text-slate-400">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9" /></svg>
                                                </span>
                                                <input
                                                    v-model="form.slug"
                                                    type="text"
                                                    required
                                                    pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                                                    class="min-w-0 flex-1 border-0 bg-transparent px-2 py-2.5 text-sm focus:outline-none dark:text-slate-100"
                                                    :placeholder="label('acme')"
                                                    autofocus
                                                />
                                                <span class="flex min-w-0 shrink items-center bg-slate-50 px-2 text-xs text-slate-500 dark:bg-slate-950 dark:text-slate-400 sm:px-3 sm:text-sm">.{{ workspaceDomainSuffix }}</span>
                                            </div>
                                            <p v-if="fieldError('slug')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('slug') }}</p>
                                        </div>

                                        <div>
                                            <label class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                                {{ label('email') }}
                                                <span class="font-normal text-slate-400 dark:text-slate-500">{{ label('optional') }}</span>
                                            </label>
                                            <div class="relative">
                                                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                                </span>
                                                <input v-model="form.email" type="email" :class="`${inputClass('email')} pl-10`" :placeholder="label('you_company_com')" />
                                            </div>
                                            <p v-if="fieldError('email')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ fieldError('email') }}</p>
                                            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{{ label('prefills_the_sign-in_form_on_your_workspace') }}</p>
                                        </div>
                                    </div>
                                </section>

                                <div class="space-y-5">
                                    <button
                                        type="submit"
                                        class="login-cta group inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:from-blue-500 hover:to-indigo-500 hover:shadow-blue-600/35 disabled:cursor-not-allowed disabled:opacity-70"
                                        :disabled="form.processing"
                                    >
                                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        <span>{{ label('continue_to_sign_in') }}</span>
                                        <svg v-if="!form.processing" class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                    </button>

                                    <div class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
                                        <span
                                            v-for="badge in trustBadges"
                                            :key="badge"
                                            class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            <svg class="h-3.5 w-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                            {{ badge }}
                                        </span>
                                    </div>

                                    <p class="text-center text-xs text-slate-500 dark:text-slate-400">
                                        {{ label('no_workspace_yet') }}
                                        <Link href="/register" class="font-medium text-blue-600 hover:text-blue-700 dark:text-blue-300">{{ label('start_your_free_trial') }}</Link>
                                    </p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CentralLayout>
</template>

<style scoped>
.login-grid {
    background-image: radial-gradient(circle at 1px 1px, rgb(148 163 184 / 0.22) 1px, transparent 0);
    background-size: 24px 24px;
}

@media (min-width: 1024px) {
    .login-card {
        min-height: 36rem;
    }
}

.login-glow {
    animation: login-float 8s ease-in-out infinite;
}

.login-glow-delayed {
    animation: login-float 8s ease-in-out 2s infinite;
}

.login-pulse-dot {
    animation: login-pulse-dot 2s ease-in-out infinite;
}

.login-cta:active:not(:disabled) {
    transform: scale(0.98);
}

@keyframes login-float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-12px); }
}

@keyframes login-pulse-dot {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.45; }
}
</style>
