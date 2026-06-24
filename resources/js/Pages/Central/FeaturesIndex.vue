<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import CentralLayout from '../../Layouts/CentralLayout.vue';

const props = defineProps({
    brand: { type: String, default: 'Helpefi' },
    trialDays: { type: Number, default: 14 },
    featurePages: { type: Array, default: () => [] },
    socialLinks: { type: Array, default: () => [] },
});

const { t } = useI18n();
const platformName = computed(() => t('app.name'));

const accentMap = {
    ai: 'border-violet-500/20 bg-violet-500/5 hover:border-violet-400/40',
    'ticket-management': 'border-blue-500/20 bg-blue-500/5 hover:border-blue-400/40',
    'knowledge-base': 'border-emerald-500/20 bg-emerald-500/5 hover:border-emerald-400/40',
    automation: 'border-amber-500/20 bg-amber-500/5 hover:border-amber-400/40',
    'live-chat': 'border-cyan-500/20 bg-cyan-500/5 hover:border-cyan-400/40',
    integrations: 'border-indigo-500/20 bg-indigo-500/5 hover:border-indigo-400/40',
    'data-residency': 'border-sky-500/20 bg-sky-500/5 hover:border-sky-400/40',
};

const cardClass = (slug) => accentMap[slug] ?? accentMap.ai;
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <section class="relative overflow-hidden bg-slate-950 py-16 text-white sm:py-24">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-32 top-0 h-[28rem] w-[28rem] rounded-full bg-violet-600/20 blur-3xl" />
                <div class="absolute -right-32 bottom-0 h-[24rem] w-[24rem] rounded-full bg-blue-600/15 blur-3xl" />
            </div>
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <nav class="mb-8 text-sm text-slate-400" aria-label="Breadcrumb">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li><Link href="/" class="transition hover:text-white">{{ platformName }}</Link></li>
                        <li aria-hidden="true">/</li>
                        <li class="text-slate-300">{{ $t('central.features_hub.nav_label') }}</li>
                    </ol>
                </nav>
                <p class="text-sm font-semibold uppercase tracking-wider text-violet-300">{{ $t('central.features_hub.badge') }}</p>
                <h1 class="mt-4 max-w-3xl text-3xl font-extrabold tracking-tight sm:text-5xl">
                    {{ $t('central.features_hub.hero_title') }}
                    <span class="bg-gradient-to-r from-violet-400 via-blue-300 to-cyan-300 bg-clip-text text-transparent">{{ $t('central.features_hub.hero_highlight') }}</span>
                </h1>
                <p class="mt-6 max-w-2xl text-lg text-slate-300">{{ $t('central.features_hub.hero_subtitle', { brand: platformName }) }}</p>
                <div class="mt-10 flex flex-wrap gap-4">
                    <Link
                        href="/register"
                        class="rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-blue-900/30 transition hover:from-blue-500 hover:to-indigo-500"
                    >
                        {{ $t('central.home.hero_cta_long') }}
                    </Link>
                    <Link href="/pricing" class="rounded-xl border border-white/20 px-6 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                        {{ $t('central.view_pricing') }}
                    </Link>
                </div>
            </div>
        </section>

        <section class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="feature in featurePages"
                        :key="feature.slug"
                        :href="feature.path"
                        class="group rounded-2xl border p-6 transition dark:border-slate-800"
                        :class="cardClass(feature.slug)"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            {{ $t(`central.feature_pages.${feature.slug}.badge`) }}
                        </p>
                        <h2 class="mt-3 text-xl font-bold text-slate-900 transition group-hover:text-blue-600 dark:text-slate-100 dark:group-hover:text-blue-400">
                            {{ $t(`central.feature_pages.${feature.slug}.nav_label`) }}
                        </h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            {{ $t(`central.feature_pages.${feature.slug}.hero_subtitle`) }}
                        </p>
                        <span class="mt-4 inline-flex text-sm font-semibold text-blue-600 dark:text-blue-400">
                            {{ $t('common.learn_more') }} →
                        </span>
                    </Link>
                </div>
            </div>
        </section>

        <section class="border-t border-slate-200 bg-slate-50 py-16 dark:border-slate-800 dark:bg-slate-950 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ $t('central.features_hub.cta_title') }}</h2>
                <p class="mt-4 text-slate-600 dark:text-slate-400">{{ $t('central.features_hub.cta_body', { days: trialDays }) }}</p>
                <Link
                    href="/register"
                    class="mt-8 inline-flex rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-3 text-sm font-bold text-white shadow-lg transition hover:from-blue-500 hover:to-indigo-500"
                >
                    {{ $t('central.home.hero_cta_short') }}
                </Link>
            </div>
        </section>
    </CentralLayout>
</template>
