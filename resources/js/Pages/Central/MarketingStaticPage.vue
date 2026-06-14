<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import { useCurrency } from '../../composables/useCurrency.js';
import { useBillingInterval } from '../../composables/useBillingInterval.js';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    page: { type: String, required: true },
    pageMeta: { type: Object, default: () => ({}) },
    plans: { type: Array, default: () => [] },
    addons: { type: Array, default: () => [] },
    featurePages: { type: Array, default: () => [] },
    contactEmail: { type: String, default: '' },
    socialLinks: { type: Array, default: () => [] },
    indiaEnabled: { type: Boolean, default: false },
    currency: { type: Object, default: () => ({}) },
    baseCurrency: { type: Object, default: () => ({}) },
    indiaCurrency: { type: Object, default: () => ({}) },
});

const { t, tm } = useI18n();
const copyPrefix = computed(() => `central.static_pages.${props.page}`);
const platformName = computed(() => t('app.name'));
const isPricing = computed(() => props.page === 'pricing');
const isLegalPage = computed(() => ['privacy', 'terms'].includes(props.page));

const effectiveDate = computed(() => {
    const value = t(`${copyPrefix.value}.effective_date`);
    return value !== `${copyPrefix.value}.effective_date` ? value : '';
});

const copy = computed(() => ({
    navLabel: t(`${copyPrefix.value}.nav_label`),
    heroTitle: t(`${copyPrefix.value}.hero_title`),
    heroSubtitle: t(`${copyPrefix.value}.hero_subtitle`),
    ctaTitle: t(`${copyPrefix.value}.cta_title`),
    ctaBody: t(`${copyPrefix.value}.cta_body`),
}));

const sections = computed(() => {
    const value = tm(`${copyPrefix.value}.sections`);
    return Array.isArray(value) ? value : [];
});

const selectedCurrencyCode = ref(props.currency?.code ?? props.baseCurrency.code);

const activeCurrency = computed(() => (
    selectedCurrencyCode.value === props.indiaCurrency.code
        ? props.indiaCurrency
        : props.baseCurrency
));

const isIndia = computed(() => (
    props.indiaEnabled && selectedCurrencyCode.value === props.indiaCurrency.code
));

const { formatPrice } = useCurrency(() => activeCurrency.value);
const { billingInterval, setBillingInterval, planPrice } = useBillingInterval();

const pricedPlans = computed(() => props.plans.filter((plan) => !plan.custom_pricing));
const customPlans = computed(() => props.plans.filter((plan) => plan.custom_pricing));
</script>

<template>
    <CentralLayout :brand="brand" :trial-days="trialDays" :social-links="socialLinks">
        <section class="relative overflow-hidden bg-slate-950 py-16 text-white sm:py-24">
            <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <nav class="mb-8 text-sm text-slate-400" aria-label="Breadcrumb">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li><Link href="/" class="transition hover:text-white">{{ platformName }}</Link></li>
                        <li aria-hidden="true">/</li>
                        <li class="text-slate-300">{{ copy.navLabel }}</li>
                    </ol>
                </nav>

                <div class="max-w-3xl">
                    <h1 class="text-3xl font-extrabold tracking-tight sm:text-5xl">{{ copy.heroTitle }}</h1>
                    <p class="mt-6 text-lg leading-relaxed text-slate-300">{{ $t(`${copyPrefix}.hero_subtitle`, { days: trialDays }) }}</p>
                    <p v-if="effectiveDate" class="mt-4 text-sm text-slate-400">{{ effectiveDate }}</p>
                    <div v-if="page === 'contact'" class="mt-8">
                        <a :href="`mailto:${contactEmail}`" class="inline-flex rounded-2xl bg-white px-8 py-4 text-sm font-bold text-slate-900">{{ contactEmail }}</a>
                    </div>
                    <div v-else-if="isPricing" class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link href="/register" class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white">Start {{ trialDays }}-day free trial</Link>
                        <a href="/#features" class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-8 py-4 text-sm font-semibold text-white transition hover:bg-white/10">Explore features</a>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="isPricing && pricedPlans.length" class="bg-slate-950 pb-16 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-8 flex justify-center">
                    <div class="inline-flex rounded-xl border border-white/10 bg-white/5 p-1">
                        <button type="button" class="rounded-lg px-4 py-2 text-sm font-semibold transition" :class="billingInterval === 'month' ? 'bg-white text-slate-900' : 'text-slate-300'" @click="setBillingInterval('month')">Monthly</button>
                        <button type="button" class="rounded-lg px-4 py-2 text-sm font-semibold transition" :class="billingInterval === 'year' ? 'bg-white text-slate-900' : 'text-slate-300'" @click="setBillingInterval('year')">Yearly</button>
                    </div>
                </div>
                <div class="grid gap-6 lg:grid-cols-3">
                    <article v-for="plan in pricedPlans" :key="plan.slug" class="rounded-2xl border border-white/10 bg-white/5 p-6">
                        <h2 class="text-xl font-bold">{{ plan.name }}</h2>
                        <p class="mt-2 text-sm text-slate-400">{{ plan.description }}</p>
                        <p class="mt-6 text-3xl font-extrabold">{{ formatPrice(planPrice(plan, isIndia)) }}</p>
                        <Link href="/register" class="mt-6 inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-bold text-white">Start trial</Link>
                    </article>
                </div>
            </div>
        </section>

        <section v-if="sections.length" class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <article v-for="(section, index) in sections" :key="index" class="mb-10 last:mb-0">
                    <h2 v-if="section.title" class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ section.title }}</h2>
                    <p v-if="section.body" class="mt-4 text-base leading-relaxed text-slate-600 dark:text-slate-400">{{ section.body }}</p>
                    <p
                        v-for="(paragraph, paragraphIndex) in section.paragraphs ?? []"
                        :key="`${index}-p-${paragraphIndex}`"
                        class="mt-4 text-base leading-relaxed text-slate-600 dark:text-slate-400"
                    >
                        {{ paragraph }}
                    </p>
                    <ul v-if="section.items?.length" class="mt-4 list-disc space-y-2 pl-6 text-base leading-relaxed text-slate-600 dark:text-slate-400">
                        <li v-for="(item, itemIndex) in section.items" :key="`${index}-i-${itemIndex}`">{{ item }}</li>
                    </ul>
                </article>
            </div>
        </section>

        <section v-if="featurePages.length && !isPricing && !isLegalPage" class="border-t border-slate-200 bg-slate-50 py-12 dark:border-slate-800 dark:bg-slate-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $t('central.explore_product_features') }}</h2>
                <div class="mt-4 flex flex-wrap gap-3">
                    <Link
                        v-for="feature in featurePages"
                        :key="feature.slug"
                        :href="feature.path"
                        class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        {{ $t(`central.feature_pages.${feature.slug}.nav_label`) }}
                    </Link>
                </div>
            </div>
        </section>

        <section v-if="copy.ctaTitle" class="bg-slate-950 py-16">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h2 class="text-3xl font-bold text-white">{{ copy.ctaTitle }}</h2>
                <p class="mt-4 text-lg text-slate-400">{{ copy.ctaBody }}</p>
                <Link href="/register" class="mt-8 inline-flex rounded-2xl bg-white px-10 py-4 text-sm font-bold text-slate-900">Start {{ trialDays }}-day free trial</Link>
            </div>
        </section>
    </CentralLayout>
</template>
