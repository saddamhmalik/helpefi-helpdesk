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

const { t, tm, te } = useI18n();
const copyPrefix = computed(() => `central.static_pages.${props.page}`);
const platformName = computed(() => t('app.name'));
const isPricing = computed(() => props.page === 'pricing');
const isLegalPage = computed(() => ['privacy', 'terms'].includes(props.page));

const effectiveDate = computed(() => (
    te(`${copyPrefix.value}.effective_date`) ? t(`${copyPrefix.value}.effective_date`) : ''
));

const copy = computed(() => ({
    navLabel: t(`${copyPrefix.value}.nav_label`),
    heroTitle: t(`${copyPrefix.value}.hero_title`),
    heroSubtitle: t(`${copyPrefix.value}.hero_subtitle`),
    ctaTitle: t(`${copyPrefix.value}.cta_title`),
    ctaBody: t(`${copyPrefix.value}.cta_body`),
}));

const subtitleParams = computed(() => {
    const params = { days: props.trialDays };

    if (props.contactEmail) {
        params.contactEmail = props.contactEmail;
    }

    return params;
});

const interpolatePageCopy = (value) => {
    if (typeof value !== 'string') {
        return value;
    }

    let copy = value;

    if (props.contactEmail) {
        copy = copy.replaceAll('{contactEmail}', props.contactEmail);
    }

    return copy;
};

const sections = computed(() => {
    const value = tm(`${copyPrefix.value}.sections`);

    if (!Array.isArray(value)) {
        return [];
    }

    return value.map((section) => ({
        ...section,
        title: interpolatePageCopy(section.title),
        body: interpolatePageCopy(section.body),
        paragraphs: Array.isArray(section.paragraphs)
            ? section.paragraphs.map(interpolatePageCopy)
            : section.paragraphs,
        items: Array.isArray(section.items)
            ? section.items.map(interpolatePageCopy)
            : section.items,
    }));
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

const setCurrency = (code) => {
    selectedCurrencyCode.value = code;
    document.cookie = `pricing_currency=${code};path=/;max-age=${60 * 60 * 24 * 365};samesite=lax`;
};

const contactHref = computed(() => (props.contactEmail ? `mailto:${props.contactEmail}` : '/register'));

const { formatPrice } = useCurrency(() => activeCurrency.value);
const { billingInterval, intervalSuffix, planPrice, yearlySavingsPercent } = useBillingInterval();

const planTaglines = computed(() => {
    const value = tm('central.home.plan_taglines');
    return value && typeof value === 'object' ? value : {};
});

const featureLabels = computed(() => {
    const value = tm('central.home.feature_labels');
    return value && typeof value === 'object' ? value : {};
});

const formatLimit = (value) => (
    value === null || value === 'unlimited'
        ? t('central.home.plan_limits.unlimited')
        : value
);

const planHighlights = (plan) => {
    const labels = featureLabels.value;
    const agents = formatLimit(plan.limits?.agents);
    const tickets = formatLimit(plan.limits?.tickets_monthly);
    const items = [
        t('central.home.plan_limits.team_members', { count: agents }),
        t('central.home.plan_limits.tickets_per_month', { count: tickets }),
    ];

    (plan.features ?? []).forEach((key) => {
        if (labels[key]) {
            items.push(labels[key]);
        }
    });

    return items;
};

const pricedPlans = computed(() => props.plans.filter((plan) => !plan.custom_pricing));
const customPlans = computed(() => props.plans.filter((plan) => plan.custom_pricing));

const addonPrice = (addon) => (
    isIndia.value
        ? (addon.price_monthly_india ?? addon.price_monthly ?? 0)
        : (addon.price_monthly ?? 0)
);
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
                    <p class="mt-6 text-lg leading-relaxed text-slate-300">{{ $t(`${copyPrefix}.hero_subtitle`, subtitleParams) }}</p>
                    <p v-if="effectiveDate" class="mt-4 text-sm text-slate-400">{{ effectiveDate }}</p>
                    <div v-if="isPricing" class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link href="/register" class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white">Start {{ trialDays }}-day free trial</Link>
                        <a href="/#features" class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-8 py-4 text-sm font-semibold text-white transition hover:bg-white/10">Explore features</a>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="isPricing && pricedPlans.length" class="bg-slate-950 pb-16 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-8 flex flex-col items-center">
                    <div class="inline-flex rounded-xl border border-white/10 bg-white/5 p-1 backdrop-blur">
                        <button
                            type="button"
                            class="rounded-lg px-5 py-2.5 text-sm font-semibold transition"
                            :class="billingInterval === 'month' ? 'bg-white text-slate-900 shadow-lg' : 'text-slate-300 hover:text-white'"
                            @click="billingInterval = 'month'"
                        >{{ $t('central.monthly') }}</button>
                        <button
                            type="button"
                            class="rounded-lg px-5 py-2.5 text-sm font-semibold transition"
                            :class="billingInterval === 'year' ? 'bg-white text-slate-900 shadow-lg' : 'text-slate-300 hover:text-white'"
                            @click="billingInterval = 'year'"
                        >{{ $t('central.yearly') }}</button>
                    </div>
                    <p v-if="billingInterval === 'year'" class="mt-3 text-sm font-semibold text-emerald-400">{{ $t('central.save_up_to_2_months_with_annual_billing') }}</p>
                    <div v-if="indiaEnabled" class="mt-4 flex items-center justify-center gap-2 text-sm text-slate-400">
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="9" />
                            <path stroke-linecap="round" d="M3 12h18M12 3c2.5 2.7 2.5 15.3 0 18M12 3c-2.5 2.7-2.5 15.3 0 18" />
                        </svg>
                        <span>{{ $t('central.show_prices_in') }}</span>
                        <div class="inline-flex overflow-hidden rounded-lg border border-white/10">
                            <button
                                type="button"
                                class="px-3 py-1 text-xs font-semibold transition"
                                :class="selectedCurrencyCode === baseCurrency.code ? 'bg-white text-slate-900' : 'text-slate-300 hover:text-white'"
                                @click="setCurrency(baseCurrency.code)"
                            >{{ baseCurrency.symbol }} {{ baseCurrency.code }}</button>
                            <button
                                type="button"
                                class="px-3 py-1 text-xs font-semibold transition"
                                :class="selectedCurrencyCode === indiaCurrency.code ? 'bg-white text-slate-900' : 'text-slate-300 hover:text-white'"
                                @click="setCurrency(indiaCurrency.code)"
                            >{{ indiaCurrency.symbol }} {{ indiaCurrency.code }}</button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap justify-center gap-8">
                    <article
                        v-for="plan in pricedPlans"
                        :key="plan.slug"
                        class="relative flex w-full flex-col rounded-3xl border p-6 sm:w-80 sm:p-8 transition"
                        :class="plan.slug === 'professional'
                            ? 'border-blue-500/50 bg-gradient-to-b from-blue-600/20 to-slate-900/80 shadow-2xl shadow-blue-600/20 ring-2 ring-blue-500/40 lg:scale-105'
                            : 'border-white/10 bg-white/5 backdrop-blur hover:border-white/20'"
                    >
                        <span v-if="plan.slug === 'professional'" class="absolute -top-3.5 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-blue-500 to-indigo-500 px-4 py-1 text-xs font-bold text-white shadow-lg">{{ $t('central.most_popular') }}</span>
                        <h2 class="text-xl font-bold text-white">{{ plan.name }}</h2>
                        <p v-if="planTaglines[plan.slug]" class="mt-1 text-sm text-slate-400">{{ planTaglines[plan.slug] }}</p>
                        <p class="mt-5 flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold tracking-tight text-white sm:text-5xl">{{ formatPrice(planPrice(plan, isIndia)) }}</span>
                            <span class="text-slate-400">{{ intervalSuffix }}</span>
                        </p>
                        <p v-if="billingInterval === 'year' && yearlySavingsPercent(plan, isIndia) > 0" class="mt-2 text-sm font-semibold text-emerald-400">
                            {{ $t('central.home.pricing_section.save_vs_monthly', { percent: yearlySavingsPercent(plan, isIndia) }) }}
                        </p>
                        <ul class="mt-8 flex-1 space-y-3">
                            <li v-for="item in planHighlights(plan)" :key="item" class="flex items-start gap-2.5 text-sm text-slate-300">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                {{ item }}
                            </li>
                        </ul>
                        <Link
                            href="/register"
                            class="mt-8 block rounded-2xl py-3.5 text-center text-sm font-bold transition"
                            :class="plan.slug === 'professional'
                                ? 'bg-white text-slate-900 shadow-xl hover:bg-slate-100'
                                : 'border border-white/20 text-white hover:bg-white/10'"
                        >
                            {{ $t('central.home.pricing_section.start_trial', { days: trialDays }) }}
                        </Link>
                        <p class="mt-3 text-center text-xs text-slate-500">{{ $t('central.no_credit_card_required') }}</p>
                    </article>
                </div>

                <div
                    v-for="plan in customPlans"
                    :key="plan.slug"
                    class="mx-auto mt-8 max-w-5xl overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-white/10 to-white/5 p-6 backdrop-blur sm:p-8"
                >
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="lg:max-w-2xl">
                            <div class="flex flex-wrap items-center gap-3">
                                <h3 class="text-2xl font-bold text-white">{{ plan.name }}</h3>
                                <span class="rounded-full border border-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-200">{{ $t('central.custom_pricing_price') }}</span>
                            </div>
                            <p v-if="planTaglines[plan.slug]" class="mt-2 text-sm text-slate-400">{{ planTaglines[plan.slug] }}</p>
                            <ul class="mt-5 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                                <li v-for="item in planHighlights(plan)" :key="item" class="flex items-start gap-2.5 text-sm text-slate-300">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                        <div class="shrink-0 text-center lg:text-right">
                            <a
                                :href="contactHref"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-white px-8 py-3.5 text-sm font-bold text-slate-900 shadow-xl transition hover:bg-slate-100 sm:w-auto"
                            >
                                {{ $t('central.contact_us') }}
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                            </a>
                            <p class="mt-3 text-xs text-slate-400">{{ $t('central.custom_pricing_cta_hint') }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="addons.length" class="mx-auto mt-16 max-w-5xl">
                    <div class="text-center">
                        <p class="text-sm font-semibold uppercase tracking-wider text-violet-400">{{ $t('central.pricing_addons_label') }}</p>
                        <h3 class="mt-2 text-2xl font-bold text-white sm:text-3xl">{{ $t('central.pricing_addons_title') }}</h3>
                        <p class="mx-auto mt-3 max-w-2xl text-sm text-slate-400">{{ $t('central.pricing_addons_subtitle') }}</p>
                    </div>
                    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <article
                            v-for="addon in addons"
                            :key="addon.key"
                            class="flex flex-col rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur transition hover:border-white/20"
                        >
                            <h4 class="text-lg font-bold text-white">{{ addon.name }}</h4>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-400">{{ addon.description }}</p>
                            <p class="mt-5 text-2xl font-extrabold text-white">
                                {{ formatPrice(addonPrice(addon)) }}
                                <span class="text-sm font-medium text-slate-400">{{ $t('central.pricing_addon_per_month') }}</span>
                            </p>
                        </article>
                    </div>
                    <p class="mt-8 text-center text-sm text-slate-400">
                        <Link href="/features/data-residency" class="font-semibold text-sky-300 transition hover:text-sky-200">
                            {{ $t('central.feature_pages.data-residency.nav_label') }} →
                        </Link>
                    </p>
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
