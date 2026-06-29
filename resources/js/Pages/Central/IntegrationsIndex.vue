<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import CentralBreadcrumbs from '../../Components/Central/CentralBreadcrumbs.vue';

const props = defineProps({
    brand: { type: String, default: 'Helpefi' },
    trialDays: { type: Number, default: 14 },
    integrationPages: { type: Array, default: () => [] },
    integrationsHub: { type: Object, default: () => ({}) },
    marketingLabels: { type: Object, default: () => ({}) },
    socialLinks: { type: Array, default: () => [] },
});

const page = usePage();
const chrome = computed(() => page.props.marketingChrome ?? {});

const accentMap = {
    slack: 'border-purple-500/20 bg-purple-500/5 hover:border-purple-400/40',
    teams: 'border-indigo-500/20 bg-indigo-500/5 hover:border-indigo-400/40',
    jira: 'border-blue-500/20 bg-blue-500/5 hover:border-blue-400/40',
    linear: 'border-violet-500/20 bg-violet-500/5 hover:border-violet-400/40',
    shopify: 'border-emerald-500/20 bg-emerald-500/5 hover:border-emerald-400/40',
    hubspot: 'border-orange-500/20 bg-orange-500/5 hover:border-orange-400/40',
    salesforce: 'border-sky-500/20 bg-sky-500/5 hover:border-sky-400/40',
    zapier: 'border-amber-500/20 bg-amber-500/5 hover:border-amber-400/40',
};

const cardClass = (slug) => accentMap[slug] ?? accentMap.slack;
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <section class="relative overflow-hidden bg-slate-950 py-16 text-white sm:py-24">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-32 top-0 h-[28rem] w-[28rem] rounded-full bg-indigo-600/20 blur-3xl" />
                <div class="absolute -right-32 bottom-0 h-[24rem] w-[24rem] rounded-full bg-purple-600/15 blur-3xl" />
            </div>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <CentralBreadcrumbs />
                <p class="text-sm font-semibold uppercase tracking-wider text-indigo-300">{{ integrationsHub.badge }}</p>
                <h1 class="mt-4 max-w-3xl text-3xl font-extrabold tracking-tight sm:text-5xl">
                    {{ integrationsHub.hero_title }}
                    <span class="bg-gradient-to-r from-indigo-400 via-purple-300 to-blue-300 bg-clip-text text-transparent">{{ integrationsHub.hero_highlight }}</span>
                </h1>
                <p class="mt-6 max-w-2xl text-lg text-slate-300">{{ integrationsHub.hero_subtitle }}</p>
                <div class="mt-10 flex flex-wrap gap-4">
                    <Link
                        href="/register"
                        class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-blue-900/30 transition hover:from-blue-500 hover:to-indigo-500"
                    >
                        {{ marketingLabels.hero_cta_long ?? 'Start free trial' }}
                    </Link>
                    <Link href="/pricing" class="rounded-xl border border-white/20 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                        {{ marketingLabels.view_pricing ?? 'View pricing' }}
                    </Link>
                </div>
            </div>
        </section>

        <section class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="integration in integrationPages"
                        :key="integration.slug"
                        :href="integration.path"
                        class="group rounded-2xl border p-6 transition dark:border-slate-800"
                        :class="cardClass(integration.slug)"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            {{ integration.badge }}
                        </p>
                        <h2 class="mt-3 text-xl font-bold text-slate-900 transition group-hover:text-blue-600 dark:text-slate-100 dark:group-hover:text-blue-400">
                            {{ integration.nav_label }}
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            {{ integration.hero_subtitle }}
                        </p>
                        <span class="mt-4 inline-flex text-sm font-semibold text-blue-600 dark:text-blue-400">
                            {{ chrome.learn_more ?? 'Learn more' }} →
                        </span>
                    </Link>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-200 bg-slate-50 py-16 dark:border-slate-800 dark:bg-slate-950 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ integrationsHub.cta_title }}</h2>
                <p class="mt-4 text-slate-600 dark:text-slate-400">{{ integrationsHub.cta_body }}</p>
                <Link
                    href="/register"
                    class="mt-8 inline-flex rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-3 text-sm font-bold text-white shadow-lg transition hover:from-blue-500 hover:to-indigo-500"
                >
                    {{ marketingLabels.hero_cta_short ?? 'Start free trial' }}
                </Link>
            </div>
        </section>
    </CentralLayout>
</template>
