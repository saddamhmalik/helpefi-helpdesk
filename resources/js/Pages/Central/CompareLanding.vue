<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import { useMarketingCopy } from '../../composables/useMarketingCopy.js';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    competitor: { type: String, required: true },
    compareMeta: { type: Object, default: () => ({}) },
    socialLinks: { type: Array, default: () => [] },
});

const { t, tm } = useI18n();
const { platformName, brandParams, createLocalizedSection } = useMarketingCopy(() => props.trialDays);

const seoPage = computed(() => props.compareMeta.seo_key ?? `compare_${props.competitor.replace(/-/g, '_')}`);

const copyPrefix = computed(() => `central.comparisons.${props.competitor}`);

const competitorName = computed(() => t(`${copyPrefix.value}.competitor_name`));

const copy = computed(() => ({
    badge: t(`${copyPrefix.value}.badge`, brandParams.value),
    heroTitle: t(`${copyPrefix.value}.hero_title`),
    heroHighlight: t(`${copyPrefix.value}.hero_highlight`),
    heroSubtitle: t(`${copyPrefix.value}.hero_subtitle`, brandParams.value),
    ctaTitle: t(`${copyPrefix.value}.cta_title`, brandParams.value),
    ctaBody: t(`${copyPrefix.value}.cta_body`, brandParams.value),
}));

const reasons = createLocalizedSection(() => `${copyPrefix.value}.reasons`);

const rows = computed(() => {
    const value = tm(`${copyPrefix.value}.rows`);

    return Array.isArray(value) ? value : [];
});

const faqs = createLocalizedSection(() => `${copyPrefix.value}.faq`);

const accent = computed(() => {
    const map = {
        blue: {
            glow: 'bg-blue-600/25',
            badge: 'border-blue-400/30 bg-blue-500/10 text-blue-300',
            highlight: 'from-blue-400 via-indigo-300 to-violet-400',
        },
        teal: {
            glow: 'bg-teal-600/25',
            badge: 'border-teal-400/30 bg-teal-500/10 text-teal-300',
            highlight: 'from-teal-400 via-emerald-300 to-green-400',
        },
        crimson: {
            glow: 'bg-red-600/20',
            badge: 'border-red-400/30 bg-red-500/10 text-red-300',
            highlight: 'from-red-400 via-orange-300 to-amber-400',
        },
        indigo: {
            glow: 'bg-indigo-600/25',
            badge: 'border-indigo-400/30 bg-indigo-500/10 text-indigo-300',
            highlight: 'from-indigo-400 via-violet-300 to-purple-400',
        },
        amber: {
            glow: 'bg-amber-600/20',
            badge: 'border-amber-400/30 bg-amber-500/10 text-amber-300',
            highlight: 'from-amber-400 via-orange-300 to-yellow-400',
        },
        orange: {
            glow: 'bg-orange-600/20',
            badge: 'border-orange-400/30 bg-orange-500/10 text-orange-300',
            highlight: 'from-orange-400 via-amber-300 to-yellow-400',
        },
    };

    return map[props.compareMeta.accent ?? 'blue'] ?? map.blue;
});

const openFaq = ref(null);

const toggleFaq = (index) => {
    openFaq.value = openFaq.value === index ? null : index;
};

const formatCell = (value) => {
    if (value === true) {
        return 'yes';
    }

    if (value === false) {
        return 'no';
    }

    return String(value);
};

const isCheck = (value) => value === true;

const isDash = (value) => value === false;
</script>

<template>
    <CentralLayout :brand="platformName" :trial-days="trialDays" :social-links="socialLinks">
        <section class="relative overflow-hidden bg-slate-950 text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-32 top-0 h-[28rem] w-[28rem] rounded-full blur-3xl" :class="accent.glow" />
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff06_1px,transparent_1px),linear-gradient(to_bottom,#ffffff06_1px,transparent_1px)] bg-[size:3.5rem_3.5rem]" />
            </div>

            <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
                <nav class="mb-8 text-sm text-slate-400" aria-label="Breadcrumb">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li><Link href="/" class="transition hover:text-white">{{ platformName }}</Link></li>
                        <li aria-hidden="true">/</li>
                        <li><Link href="/#compare" class="transition hover:text-white">{{ $t('central.compare_nav') }}</Link></li>
                        <li aria-hidden="true">/</li>
                        <li class="text-slate-300">{{ competitorName }}</li>
                    </ol>
                </nav>

                <div class="max-w-3xl">
                    <span class="inline-flex rounded-full border px-4 py-1.5 text-xs font-semibold backdrop-blur" :class="accent.badge">
                        {{ copy.badge }}
                    </span>
                    <h1 class="mt-6 text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                        {{ copy.heroTitle }}
                        <span class="mt-2 block bg-gradient-to-r bg-clip-text text-transparent" :class="accent.highlight">
                            {{ copy.heroHighlight }}
                        </span>
                    </h1>
                    <p class="mt-6 text-lg leading-relaxed text-slate-300">
                        {{ copy.heroSubtitle }}
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Link
                            href="/register"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white shadow-2xl shadow-blue-600/40 transition hover:from-blue-500 hover:to-indigo-500"
                        >
                            Start {{ trialDays }}-day free trial
                        </Link>
                        <Link
                            href="/#pricing"
                            class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-8 py-4 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            {{ $t('central.view_pricing') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ $t('central.why_teams_switch') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ $t('central.compare_why_switch_title', { competitor: competitorName }) }}
                    </h2>
                </div>
                <div class="mt-10 grid gap-6 md:grid-cols-3">
                    <article
                        v-for="(reason, index) in reasons"
                        :key="index"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ reason.title }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ reason.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="bg-slate-950 py-16 sm:py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-400">{{ $t('central.feature_comparison') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">
                        {{ platformName }} vs {{ competitorName }}
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-sm text-slate-400">
                        {{ $t('central.compare_disclaimer') }}
                    </p>
                </div>

                <div class="mt-12 space-y-3 lg:hidden">
                    <article
                        v-for="row in rows"
                        :key="row.feature"
                        class="rounded-2xl border border-white/10 bg-white/5 p-4"
                    >
                        <p class="text-sm font-medium text-slate-200">{{ row.feature }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-center text-xs">
                            <div class="rounded-xl bg-blue-500/10 px-3 py-2 ring-1 ring-blue-500/20">
                                <p class="font-semibold text-blue-300">{{ platformName }}</p>
                                <p class="mt-1 text-lg">
                                    <span v-if="isCheck(row.us)" class="text-emerald-400">✓</span>
                                    <span v-else-if="isDash(row.us)" class="text-slate-600">—</span>
                                    <span v-else class="text-slate-300">{{ row.us }}</span>
                                </p>
                            </div>
                            <div class="rounded-xl bg-white/5 px-3 py-2 ring-1 ring-white/10">
                                <p class="font-semibold text-slate-400">{{ competitorName }}</p>
                                <p class="mt-1 text-lg">
                                    <span v-if="isCheck(row.them)" class="text-emerald-400">✓</span>
                                    <span v-else-if="isDash(row.them)" class="text-slate-600">—</span>
                                    <span v-else class="text-slate-500">{{ row.them }}</span>
                                </p>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="mt-12 hidden overflow-hidden rounded-2xl border border-white/10 lg:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-white/10 bg-white/5">
                                <th class="px-6 py-4 text-left font-medium text-slate-400">{{ $t('central.capability') }}</th>
                                <th class="px-6 py-4 text-center font-semibold text-blue-400">{{ platformName }}</th>
                                <th class="px-6 py-4 text-center font-medium text-slate-400">{{ competitorName }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in rows"
                                :key="row.feature"
                                class="border-b border-white/5 transition hover:bg-white/5"
                            >
                                <td class="px-6 py-4 text-slate-300">{{ row.feature }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span v-if="isCheck(row.us)" class="text-lg text-emerald-400">✓</span>
                                    <span v-else-if="isDash(row.us)" class="text-slate-600">—</span>
                                    <span v-else class="font-medium text-slate-200">{{ row.us }}</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span v-if="isCheck(row.them)" class="text-lg text-emerald-400">✓</span>
                                    <span v-else-if="isDash(row.them)" class="text-slate-600">—</span>
                                    <span v-else class="text-slate-500">{{ row.them }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ $t('central.faq') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $t('central.common_questions') }}</h2>
                </div>
                <div class="mt-10 space-y-3">
                    <div
                        v-for="(item, index) in faqs"
                        :key="item.q"
                        class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-950"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left"
                            @click="toggleFaq(index)"
                        >
                            <span class="text-sm font-semibold text-slate-900 dark:text-slate-100 sm:text-base">{{ item.q }}</span>
                            <svg class="h-5 w-5 shrink-0 text-slate-400 transition" :class="openFaq === index ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                        <div v-show="openFaq === index" class="border-t border-slate-200 px-5 pb-5 pt-2 dark:border-slate-800">
                            <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.a }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-slate-950 py-16 sm:py-24">
            <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ copy.ctaTitle }}</h2>
                <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-400">{{ copy.ctaBody }}</p>
                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link
                        href="/register"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-white px-10 py-4 text-sm font-bold text-slate-900 shadow-xl transition hover:bg-slate-100 sm:w-auto"
                    >
                        Start {{ trialDays }}-day free trial
                    </Link>
                    <Link
                        href="/"
                        class="inline-flex w-full items-center justify-center rounded-2xl border border-white/20 px-10 py-4 text-sm font-semibold text-white transition hover:bg-white/10 sm:w-auto"
                    >
                        {{ $t('central.explore_product') }}
                    </Link>
                </div>
            </div>
        </section>
    </CentralLayout>
</template>
