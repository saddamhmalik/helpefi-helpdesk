<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CentralLayout from '../../Layouts/CentralLayout.vue';
import { formatMarketingTemplate } from '../../composables/useMarketingEnglish.js';
import CentralBreadcrumbs from '../../Components/Central/CentralBreadcrumbs.vue';
import AuthorCard from '../../Components/Central/AuthorCard.vue';
import ComparisonTable from '../../Components/Central/ComparisonTable.vue';
import CtaSection from '../../Components/Central/CtaSection.vue';
import FaqAccordion from '../../Components/Central/FaqAccordion.vue';

const props = defineProps({
    brand: { type: String, default: 'helpefi' },
    trialDays: { type: Number, default: 14 },
    competitor: { type: String, required: true },
    compareMeta: { type: Object, default: () => ({}) },
    content: { type: Object, required: true },
    socialLinks: { type: Array, default: () => [] },
});

const page = usePage();
const chrome = computed(() => page.props.marketingChrome ?? {});
const chromeText = (key, params = {}) => formatMarketingTemplate(chrome.value[key] ?? key, { brand: props.brand, ...params });

const platformName = computed(() => props.brand);
const competitorName = computed(() => props.content.competitor_name ?? props.competitor);
const reasons = computed(() => props.content.reasons ?? []);
const rows = computed(() => props.content.rows ?? []);
const faqs = computed(() => props.content.faq ?? []);
const deepDives = computed(() => props.content.deep_dives ?? []);
const relatedLinks = computed(() => props.content.related_links ?? []);
const migrationSteps = computed(() => props.content.migration?.steps ?? []);
const whoThemParagraphs = computed(() => props.content.who_them?.paragraphs ?? []);
const whoUsParagraphs = computed(() => props.content.who_us?.paragraphs ?? []);
const prosUs = computed(() => props.content.pros?.us ?? []);
const prosThem = computed(() => props.content.pros?.them ?? []);
const consUs = computed(() => props.content.cons?.us ?? []);
const consThem = computed(() => props.content.cons?.them ?? []);
const useCases = computed(() => props.content.use_cases?.items ?? []);
const alternatives = computed(() => props.content.alternatives?.items ?? []);
const alternativesIntro = computed(() => props.content.alternatives?.intro ?? '');

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
        violet: {
            glow: 'bg-violet-600/25',
            badge: 'border-violet-400/30 bg-violet-500/10 text-violet-300',
            highlight: 'from-violet-400 via-fuchsia-300 to-pink-400',
        },
    };

    return map[props.compareMeta.accent ?? 'blue'] ?? map.blue;
});
</script>

<template>
    <CentralLayout :brand="platformName" :trial-days="trialDays" :social-links="socialLinks">
        <!-- Hero -->
        <section class="relative overflow-hidden bg-slate-950 text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-32 top-0 h-[28rem] w-[28rem] rounded-full blur-3xl" :class="accent.glow" />
                <div class="absolute inset-0 bg-[linear-gradient(to_right,#ffffff06_1px,transparent_1px),linear-gradient(to_bottom,#ffffff06_1px,transparent_1px)] bg-[size:3.5rem_3.5rem]" />
            </div>

            <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
                <CentralBreadcrumbs />

                <div class="mt-8 flex flex-wrap items-center gap-4" aria-label="Product logos">
                    <img
                        v-if="content.logo_us"
                        :src="content.logo_us"
                        :alt="`${platformName} logo`"
                        width="180"
                        height="48"
                        class="h-10 w-auto rounded-lg ring-1 ring-white/10"
                        loading="eager"
                    >
                    <span class="text-sm font-semibold uppercase tracking-wider text-slate-500">vs</span>
                    <img
                        v-if="content.logo_them"
                        :src="content.logo_them"
                        :alt="`${competitorName} logo`"
                        width="180"
                        height="48"
                        class="h-10 w-auto rounded-lg ring-1 ring-white/10"
                        loading="eager"
                    >
                </div>

                <div class="mt-8 max-w-3xl">
                    <span class="inline-flex rounded-full border px-4 py-1.5 text-xs font-semibold backdrop-blur" :class="accent.badge">
                        {{ content.badge }}
                    </span>
                    <h1 class="mt-6 text-3xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                        {{ content.hero_title }}
                        <span class="mt-2 block bg-gradient-to-r bg-clip-text text-transparent" :class="accent.highlight">
                            {{ content.hero_highlight }}
                        </span>
                    </h1>
                    <p class="mt-6 text-lg leading-relaxed text-slate-300">
                        {{ content.hero_subtitle }}
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <Link
                            href="/register"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 px-8 py-4 text-sm font-bold text-white shadow-2xl shadow-blue-600/40 transition hover:from-blue-500 hover:to-indigo-500"
                        >
                            Start {{ trialDays }}-day free trial
                        </Link>
                        <Link
                            href="/pricing"
                            class="inline-flex items-center justify-center rounded-2xl border border-white/20 px-8 py-4 text-sm font-semibold text-white transition hover:bg-white/10"
                        >
                            {{ chromeText('view_pricing') }}
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Article metadata -->
        <AuthorCard
            :updated-at="content.updated_at"
            :author="content.author ?? {}"
            :reviewer="content.reviewer ?? {}"
            :updated-label="chromeText('last_updated_on')"
            :written-label="chromeText('written_by')"
            :reviewed-label="chromeText('reviewed_by')"
        />

        <!-- Intro -->
        <section v-if="content.intro" class="bg-white py-12 dark:bg-slate-900 sm:py-16">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <p class="text-base leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ content.intro }}</p>
            </div>
        </section>

        <!-- Why switch -->
        <section class="bg-slate-50 py-16 dark:bg-slate-950 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-600">{{ chromeText('why_teams_switch') }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ chromeText('compare_why_switch_title', { competitor: competitorName }) }}
                    </h2>
                </div>
                <div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="(reason, index) in reasons"
                        :key="index"
                        class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ reason.title }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ reason.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Comparison table -->
        <ComparisonTable
            :rows="rows"
            :platform-name="platformName"
            :competitor-name="competitorName"
            :logo-us="content.logo_us"
            :logo-them="content.logo_them"
            :eyebrow="chromeText('feature_comparison')"
            :disclaimer="chromeText('compare_disclaimer')"
        />

        <!-- Deep dives -->
        <section v-if="deepDives.length" class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Detailed {{ platformName }} vs {{ competitorName }} breakdown
                </h2>
                <div class="mt-10 space-y-10">
                    <article v-for="(section, index) in deepDives" :key="index">
                        <h3 class="text-xl font-semibold text-slate-900 dark:text-slate-100">{{ section.title }}</h3>
                        <p class="mt-3 text-base leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ section.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Screenshots gallery -->
        <section v-if="content.screenshots?.length" class="bg-slate-50 py-16 dark:bg-slate-950 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ chromeText('screenshots') }}</h2>
                <div class="mt-10 grid gap-8 md:grid-cols-2">
                    <figure v-for="(shot, index) in content.screenshots" :key="index" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" :aria-label="shot.caption || shot.alt">
                        <div class="aspect-video bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 text-sm" aria-hidden="true">
                            <span class="italic">Screenshot coming soon</span>
                        </div>
                        <figcaption class="border-t border-slate-200 px-5 py-4 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-400">
                            {{ shot.caption || shot.alt }}
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- Pros / Cons -->
        <section class="bg-slate-50 py-16 dark:bg-slate-950 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Pros and cons</h2>
                <div class="mt-10 grid gap-8 lg:grid-cols-2">
                    <div class="space-y-6">
                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-6 dark:border-emerald-900 dark:bg-emerald-950/30">
                            <h3 class="text-lg font-semibold text-emerald-900 dark:text-emerald-200">{{ content.pros?.us_title || `${platformName} pros` }}</h3>
                            <ul class="mt-4 space-y-4">
                                <li v-for="(item, index) in prosUs" :key="`pu-${index}`">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ item.title }}</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.body }}</p>
                                </li>
                            </ul>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ content.cons?.us_title || `${platformName} cons` }}</h3>
                            <ul class="mt-4 space-y-4">
                                <li v-for="(item, index) in consUs" :key="`cu-${index}`">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ item.title }}</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.body }}</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div class="rounded-2xl border border-blue-200 bg-blue-50/60 p-6 dark:border-blue-900 dark:bg-blue-950/30">
                            <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-200">{{ content.pros?.them_title || `${competitorName} pros` }}</h3>
                            <ul class="mt-4 space-y-4">
                                <li v-for="(item, index) in prosThem" :key="`pt-${index}`">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ item.title }}</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.body }}</p>
                                </li>
                            </ul>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ content.cons?.them_title || `${competitorName} cons` }}</h3>
                            <ul class="mt-4 space-y-4">
                                <li v-for="(item, index) in consThem" :key="`ct-${index}`">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ item.title }}</p>
                                    <p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.body }}</p>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Best for / Who should use -->
        <section class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-2">
                    <article>
                        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ content.who_them?.title || `Who should use ${competitorName}` }}
                        </h2>
                        <div class="mt-5 space-y-4">
                            <p
                                v-for="(paragraph, index) in whoThemParagraphs"
                                :key="`wt-${index}`"
                                class="text-base leading-relaxed text-slate-700 dark:text-slate-300"
                            >
                                {{ paragraph }}
                            </p>
                        </div>
                    </article>
                    <article>
                        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ content.who_us?.title || `Best for Helpefi` }}
                        </h2>
                        <div class="mt-5 space-y-4">
                            <p
                                v-for="(paragraph, index) in whoUsParagraphs"
                                :key="`wu-${index}`"
                                class="text-base leading-relaxed text-slate-700 dark:text-slate-300"
                            >
                                {{ paragraph }}
                            </p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- Use cases -->
        <section v-if="useCases.length" class="bg-slate-50 py-16 dark:bg-slate-950 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ content.use_cases?.title || `Use cases: ${competitorName} vs ${platformName}` }}
                    </h2>
                </div>
                <div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="(item, index) in useCases"
                        :key="index"
                        class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ item.title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.body }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Alternatives -->
        <section v-if="alternatives.length" class="bg-white py-16 dark:bg-slate-900 sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ content.alternatives?.title || 'Related alternatives' }}
                    </h2>
                    <p v-if="alternativesIntro" class="mt-4 text-base leading-relaxed text-slate-700 dark:text-slate-300">
                        {{ alternativesIntro }}
                    </p>
                </div>
                <ul class="mt-10 grid gap-4 md:grid-cols-2">
                    <li v-for="(item, index) in alternatives" :key="index">
                        <Link
                            :href="item.href"
                            class="block h-full rounded-2xl border border-slate-200 bg-slate-50 p-6 transition hover:border-blue-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:hover:border-blue-700"
                        >
                            <p class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ item.name }}</p>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ item.blurb }}</p>
                        </Link>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Migration guide -->
        <section class="bg-slate-950 py-16 text-white sm:py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-sm font-semibold uppercase tracking-wider text-blue-400">Migration Guide</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight">Migrate from {{ competitorName }} to {{ platformName }}</h2>
                    <p v-if="content.migration?.intro" class="mt-4 text-base leading-relaxed text-slate-300 whitespace-pre-line">
                        {{ content.migration.intro }}
                    </p>
                </div>
                <ol class="mt-10 grid gap-6 md:grid-cols-2">
                    <li
                        v-for="(step, index) in migrationSteps"
                        :key="index"
                        class="rounded-2xl border border-white/10 bg-white/5 p-6"
                    >
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-300">Step {{ index + 1 }}</p>
                        <p class="mt-2 text-lg font-semibold text-white">{{ step.title }}</p>
                        <p class="mt-3 text-sm leading-relaxed text-slate-300">{{ step.body }}</p>
                    </li>
                </ol>
                <div v-if="content.migration?.link_href" class="mt-8">
                    <Link
                        :href="content.migration.link_href"
                        class="inline-flex items-center rounded-xl border border-white/20 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
                    >
                        {{ content.migration.link_label || 'Migration guide' }}
                    </Link>
                </div>
            </div>
        </section>

        <!-- FAQs -->
        <FaqAccordion
            :items="faqs"
            :eyebrow="chromeText('faq')"
            :title="chromeText('common_questions')"
            section-class="bg-slate-50 py-16 dark:bg-slate-950 sm:py-20"
            :inject-schema="true"
        />

        <!-- Conclusion -->
        <section v-if="content.conclusion" class="bg-white py-16 dark:bg-slate-900 sm:py-20" style="content-visibility:auto">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    {{ content.conclusion.title }}
                </h2>
                <p class="mt-5 text-base leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-line">
                    {{ content.conclusion.body }}
                </p>
                <div v-if="relatedLinks.length" class="mt-8 flex flex-wrap gap-3">
                    <Link
                        v-for="(link, index) in relatedLinks"
                        :key="index"
                        :href="link.href"
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-blue-300 hover:text-blue-700 dark:border-slate-700 dark:text-slate-300"
                    >
                        {{ link.label }}
                    </Link>
                </div>
            </div>
        </section>

        <!-- External references -->
        <section v-if="content.external_references?.length" class="border-t border-slate-200 bg-slate-50 py-12 dark:border-slate-800 dark:bg-slate-950" style="content-visibility:auto">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ chromeText('external_references') }}</h2>
                <ul class="mt-4 space-y-4">
                    <li v-for="(ref, index) in content.external_references" :key="index" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <a :href="ref.url" target="_blank" rel="noopener noreferrer" class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                            {{ ref.title }}<span class="sr-only"> (opens in new tab)</span> →
                        </a>
                        <p v-if="ref.description" class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ ref.description }}</p>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Final CTA -->
        <CtaSection
            :title="content.cta_title"
            :body="content.cta_body"
            :primary-label="`Start ${trialDays}-day free trial`"
            primary-href="/register"
            secondary-label="View pricing"
            secondary-href="/pricing"
        />
    </CentralLayout>
</template>
